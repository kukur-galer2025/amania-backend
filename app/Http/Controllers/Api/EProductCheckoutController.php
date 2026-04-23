<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EProduct;
use App\Models\EProductPurchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EProductCheckoutController extends Controller
{
    // =========================================================================
    // 1. MENGAMBIL DAFTAR METODE PEMBAYARAN DARI TRIPAY
    // =========================================================================
    public function getPaymentChannels()
    {
        try {
            $apiKey = config('tripay.api_key');
            $apiUrl = rtrim(config('tripay.api_url'), '/') . '/merchant/payment-channel';

            $response = Http::withoutVerifying()
                ->withToken($apiKey)
                ->get($apiUrl);
                
            $result = $response->json();

            if ($response->successful() && isset($result['success']) && $result['success'] == true) {
                return response()->json([
                    'success' => true,
                    'message' => 'Berhasil mengambil metode pembayaran dari Tripay.',
                    'data'    => $result['data']
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil metode pembayaran dari Tripay.',
            ], 400);

        } catch (\Exception $e) {
            Log::error('Tripay Channels Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Sistem Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // 2. FUNGSI CHECKOUT E-PRODUCT (TRIPAY CLOSED PAYMENT)
    // =========================================================================
    public function purchaseEProduct(Request $request)
    {
        $request->validate([
            'e_product_id' => 'required|exists:e_products,id',
            'method'       => 'required|string', 
        ]);

        $user    = $request->user();
        $product = EProduct::where('is_published', true)->findOrFail($request->e_product_id);

        // Cek apakah user sudah membeli
        $alreadyBought = EProductPurchase::where('user_id', $user->id)
            ->where('e_product_id', $product->id)
            ->whereIn('status', ['PAID', 'success', 'SETTLED'])
            ->exists();

        if ($alreadyBought) {
            return response()->json(['success' => false, 'message' => 'Anda sudah memiliki akses ke produk digital ini.']);
        }

        // Cek apakah ada invoice yang masih UNPAID
        $pendingInvoice = EProductPurchase::where('user_id', $user->id)
            ->where('e_product_id', $product->id)
            ->where('status', 'UNPAID')
            ->first();

        if ($pendingInvoice && $pendingInvoice->checkout_url) {
            return response()->json([
                'success'      => true,
                'message'      => 'Silakan lanjutkan pembayaran pada invoice sebelumnya.',
                'checkout_url' => $pendingInvoice->checkout_url,
            ]);
        }

        $merchantRef = 'INV-EP-' . strtoupper(Str::random(8)) . '-' . $user->id;
        $amount = (int) $product->price;

        DB::beginTransaction();
        try {
            $purchase = EProductPurchase::create([
                'reference'    => $merchantRef, 
                'user_id'      => $user->id,
                'e_product_id' => $product->id,
                'amount'       => $amount,
                'status'       => 'UNPAID',
            ]);

            // Jika produk gratis
            if ($amount == 0) {
                $purchase->update(['status' => 'PAID']);
                DB::commit();
                return response()->json([
                    'success'  => true,
                    'message'  => 'Produk gratis berhasil diklaim!',
                    'is_free'  => true,
                ]);
            }

            // ==========================================
            // 🔥 LOGIKA SIGNATURE & REQUEST TRIPAY 🔥
            // ==========================================
            $privateKey   = config('tripay.private_key');
            $merchantCode = config('tripay.merchant_code');
            $apiKey       = config('tripay.api_key');
            
            // Endpoint untuk Closed Payment Tripay
            $apiUrl       = rtrim(config('tripay.api_url'), '/') . '/transaction/create';

            // FORMAT SIGNATURE TRIPAY (Closed Payment): {MerchantCode}{MerchantRef}{Amount}
            $signature = hash_hmac('sha256', $merchantCode . $merchantRef . $amount, $privateKey);

            $payload = [
                'method'         => $request->method,
                'merchant_ref'   => $merchantRef,
                'amount'         => $amount,
                'customer_name'  => $user->name ?? 'Member Amania',
                'customer_email' => $user->email ?? 'email@amania.id',
                'customer_phone' => $user->phone ?? '08000000000',
                'order_items'    => [
                    [
                        'sku'      => 'EP-' . $product->id,
                        'name'     => substr($product->title, 0, 50),
                        'price'    => $amount,
                        'quantity' => 1,
                    ]
                ],
                // Redirect user setelah bayar di halaman Tripay
                'return_url'   => config('app.frontend_url', 'https://amania.id') . '/e-products/' . $product->slug,
                // Waktu kedaluwarsa 24 Jam
                'expired_time' => (time() + (24 * 60 * 60)),
                'signature'    => $signature
            ];

            $response = Http::withoutVerifying()
                ->withToken($apiKey)
                ->post($apiUrl, $payload);
                
            $result = $response->json();

            // Jika Tripay Berhasil
            if ($response->successful() && isset($result['success']) && $result['success'] == true) {
                $purchase->update([
                    'tripay_reference' => $result['data']['reference'], 
                    'checkout_url'     => $result['data']['checkout_url'],
                ]);
                DB::commit();

                return response()->json([
                    'success'      => true,
                    'message'      => 'Silakan lakukan pembayaran.',
                    'checkout_url' => $result['data']['checkout_url'],
                ]);
            }

            // Jika Tripay Gagal
            DB::rollBack();
            Log::error('Tripay Create Transaction Error: ', $result ?? []);
            return response()->json([
                'success' => false, 
                'message' => 'Tripay Error: ' . ($result['message'] ?? 'Gagal membuat transaksi ke payment gateway.')
            ], 400);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Checkout System Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan DB/Sistem: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 3. WEBHOOK KHUSUS E-PRODUCT (CALLBACK TRIPAY)
    // =========================================================================
    public function tripayWebhook(Request $request)
    {
        // 1. Ambil Signature dari Header
        $callbackSignature = $request->header('X-Callback-Signature') ?? $request->server('HTTP_X_CALLBACK_SIGNATURE');
        
        // 2. Ambil Body JSON murni
        $json = $request->getContent();
        
        // 3. Validasi Signature menggunakan Private Key Tripay
        $signature = hash_hmac('sha256', $json, config('tripay.private_key'));

        if ($signature !== $callbackSignature) {
            Log::warning('Tripay Webhook: Invalid Signature');
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        // 4. Validasi Event (Hanya tangkap status pembayaran)
        $event = $request->header('X-Callback-Event') ?? $request->server('HTTP_X_CALLBACK_EVENT');
        if ('payment_status' !== $event) {
            return response()->json(['success' => false, 'message' => 'Unrecognized callback event'], 400);
        }

        $data = json_decode($json);
        $merchantRef = $data->merchant_ref;
        $status = $data->status; // PAID, UNPAID, EXPIRED, FAILED

        try {
            if (Str::startsWith($merchantRef, 'INV-EP-')) {
                $purchase = EProductPurchase::where('reference', $merchantRef)->first();
                
                if (!$purchase) {
                    return response()->json(['success' => false, 'message' => 'Purchase not found'], 404);
                }

                // Update status sesuai balikan dari Tripay
                if (in_array($status, ['PAID', 'SETTLED'])) {
                    $purchase->update(['status' => 'PAID']);
                } elseif (in_array($status, ['EXPIRED', 'FAILED', 'REFUND'])) {
                    $purchase->update(['status' => $status]);
                }

            } else {
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