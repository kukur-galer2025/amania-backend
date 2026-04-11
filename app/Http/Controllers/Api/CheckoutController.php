<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EProduct;
use App\Models\EProductPurchase;
use App\Models\SkdTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    /**
     * =========================================================================
     * 1. FUNGSI CHECKOUT E-PRODUCT (MENGGUNAKAN TRIPAY)
     * =========================================================================
     */
    public function purchaseEProduct(Request $request)
    {
        // Frontend E-Product sekarang WAJIB mengirim "method" (misal: QRIS, MYBVA)
        $request->validate([
            'e_product_id' => 'required|exists:e_products,id',
            'method'       => 'required|string', 
        ]);

        $user    = $request->user();
        $product = EProduct::where('is_published', true)->findOrFail($request->e_product_id);

        $alreadyBought = EProductPurchase::where('user_id', $user->id)
            ->where('e_product_id', $product->id)
            ->where('status', 'success')
            ->exists();

        if ($alreadyBought) {
            return response()->json(['success' => false, 'message' => 'Anda sudah memiliki produk ini.'], 400);
        }

        // Prefix "INV-EP-" untuk membedakan E-Product dari SKD Tryout
        $invoiceCode = 'INV-EP-' . strtoupper(Str::random(8));

        DB::beginTransaction();
        try {
            $purchase = EProductPurchase::create([
                'invoice_code' => $invoiceCode,
                'user_id'      => $user->id,
                'e_product_id' => $product->id,
                'amount'       => $product->price,
                'status'       => 'pending',
            ]);

            if ($product->price == 0) {
                $purchase->update(['status' => 'success']);
                DB::commit();
                return response()->json([
                    'success'  => true,
                    'message'  => 'Produk gratis berhasil diklaim!',
                    'is_free'  => true,
                    'data'     => $purchase
                ]);
            }

            // --- TRIPAY LOGIC UNTUK E-PRODUCT ---
            $amount = (int) $product->price;
            $privateKey = config('tripay.private_key');
            $merchantCode = config('tripay.merchant_code');
            $signature = hash_hmac('sha256', $merchantCode . $invoiceCode . $amount, $privateKey);

            $payload = [
                'method'         => $request->method,
                'merchant_ref'   => $invoiceCode,
                'amount'         => $amount,
                'customer_name'  => $user->name ?? 'Member Amania',
                'customer_email' => $user->email ?? 'email@amania.id',
                'customer_phone' => $user->phone ?? '081234567890',
                'order_items'    => [
                    [
                        'name'     => substr($product->title, 0, 50),
                        'price'    => $amount,
                        'quantity' => 1,
                    ]
                ],
                'return_url'   => config('app.frontend_url') . '/e-products/library',
                'expired_time' => (time() + (24 * 60 * 60)),
                'signature'    => $signature
            ];

            $response = Http::withToken(config('tripay.api_key'))->post(config('tripay.api_url') . 'transaction/create', $payload);
            $result = $response->json();

            if ($response->successful() && isset($result['success']) && $result['success'] == true) {
                // Kita gunakan snap_token untuk simpan "reference" dan payment_url untuk "checkout_url"
                $purchase->update([
                    'snap_token'  => $result['data']['reference'], 
                    'payment_url' => $result['data']['checkout_url'],
                ]);
                DB::commit();

                return response()->json([
                    'success'     => true,
                    'message'     => 'Silakan lakukan pembayaran.',
                    'payment_url' => $result['data']['checkout_url'],
                    'reference'   => $result['data']['reference'],
                ]);
            }

            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Tripay: ' . ($result['message'] ?? 'Error')], 400);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan sistem.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * =========================================================================
     * 2. UNIVERSAL WEBHOOK TRIPAY (MENANGANI SKD & E-PRODUCT)
     * =========================================================================
     */
    public function tripayWebhook(Request $request)
    {
        $callbackSignature = $request->server('HTTP_X_CALLBACK_SIGNATURE');
        $json = $request->getContent();
        $signature = hash_hmac('sha256', $json, config('tripay.private_key'));

        // 1. Validasi Keamanan Signature
        if ($signature !== $callbackSignature) {
            Log::warning('Tripay Webhook: Invalid Signature');
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        // 2. Validasi Event
        if ('payment_status' !== $request->server('HTTP_X_CALLBACK_EVENT')) {
            return response()->json(['success' => false, 'message' => 'Unrecognized callback event'], 400);
        }

        $data = json_decode($json);
        $merchantRef = $data->merchant_ref;
        $status = $data->status; // 'PAID', 'UNPAID', 'EXPIRED', 'FAILED'

        // 3. CABANG LOGIKA BERDASARKAN PREFIX INVOICE
        try {
            if (Str::startsWith($merchantRef, 'SKD-')) {
                // --- A. LOGIKA SKD TRYOUT ---
                $transaction = SkdTransaction::where('merchant_ref', $merchantRef)->first();
                if (!$transaction) return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);

                if ($status === 'PAID') {
                    $transaction->update(['status' => 'PAID', 'paid_at' => now()]);
                } elseif (in_array($status, ['EXPIRED', 'FAILED'])) {
                    $transaction->update(['status' => $status]);
                }
            } 
            elseif (Str::startsWith($merchantRef, 'INV-EP-')) {
                // --- B. LOGIKA E-PRODUCT ---
                $purchase = EProductPurchase::where('invoice_code', $merchantRef)->first();
                if (!$purchase) return response()->json(['success' => false, 'message' => 'Purchase not found'], 404);

                // Tabel EProductPurchase menggunakan status: 'pending', 'success', 'failed'
                if ($status === 'PAID') {
                    $purchase->update(['status' => 'success']);
                } elseif (in_array($status, ['EXPIRED', 'FAILED'])) {
                    $purchase->update(['status' => 'failed']);
                }
            } 
            else {
                Log::warning('Tripay Webhook: Unknown Merchant Ref format => ' . $merchantRef);
                return response()->json(['success' => false, 'message' => 'Format Merchant Ref tidak dikenali'], 400);
            }

            return response()->json(['success' => true, 'message' => 'Status transaksi berhasil diupdate.']);

        } catch (\Exception $e) {
            Log::error('Tripay Webhook Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Internal server error'], 500);
        }
    }
}