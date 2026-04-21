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

            $response = Http::withoutVerifying()->withToken($apiKey)->get($apiUrl);
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
                'message' => 'Gagal terhubung ke Tripay.',
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
    // 2. FUNGSI CHECKOUT E-PRODUCT (TRIPAY)
    // =========================================================================
    public function purchaseEProduct(Request $request)
    {
        $request->validate([
            'e_product_id' => 'required|exists:e_products,id',
            'method'       => 'required|string', 
        ]);

        $user    = $request->user();
        $product = EProduct::where('is_published', true)->findOrFail($request->e_product_id);

        $alreadyBought = EProductPurchase::where('user_id', $user->id)
            ->where('e_product_id', $product->id)
            ->whereIn('status', ['PAID', 'success'])
            ->exists();

        if ($alreadyBought) {
            return response()->json(['success' => false, 'message' => 'Anda sudah memiliki akses ke produk digital ini.']);
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

            if ($amount == 0) {
                $purchase->update(['status' => 'PAID']);
                DB::commit();
                return response()->json([
                    'success'  => true,
                    'message'  => 'Produk gratis berhasil diklaim!',
                    'is_free'  => true,
                ]);
            }

            $privateKey   = config('tripay.private_key');
            $merchantCode = config('tripay.merchant_code');
            $apiKey       = config('tripay.api_key');
            $apiUrl       = rtrim(config('tripay.api_url'), '/') . '/transaction/create';

            $signature = hash_hmac('sha256', $merchantCode . $merchantRef . $amount, $privateKey);

            $payload = [
                'method'         => $request->method,
                'merchant_ref'   => $merchantRef,
                'amount'         => $amount,
                'customer_name'  => $user->name ?? 'Member Amania',
                'customer_email' => $user->email ?? 'email@amania.id',
                'customer_phone' => $user->phone ?? '080000000000',
                'order_items'    => [
                    [
                        'sku'      => 'EP-' . $product->id,
                        'name'     => substr($product->title, 0, 50),
                        'price'    => $amount,
                        'quantity' => 1,
                    ]
                ],
                'return_url'   => config('app.frontend_url', 'http://localhost:3000') . '/e-products/' . $product->slug,
                'expired_time' => (time() + (24 * 60 * 60)),
                'signature'    => $signature
            ];

            $response = Http::withoutVerifying()->withToken($apiKey)->post($apiUrl, $payload);
            $result = $response->json();

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

            DB::rollBack();
            Log::error('Tripay Create Transaction Error: ', $result ?? []);
            return response()->json(['success' => false, 'message' => 'Tripay: ' . ($result['message'] ?? 'Error dari payment gateway.')], 400);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Checkout System Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan DB/Sistem: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 3. WEBHOOK KHUSUS E-PRODUCT (TRIPAY)
    // =========================================================================
    public function tripayWebhook(Request $request)
    {
        $callbackSignature = $request->server('HTTP_X_CALLBACK_SIGNATURE');
        $json = $request->getContent();
        $signature = hash_hmac('sha256', $json, config('tripay.private_key'));

        if ($signature !== $callbackSignature) {
            Log::warning('Tripay Webhook: Invalid Signature');
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        if ('payment_status' !== $request->server('HTTP_X_CALLBACK_EVENT')) {
            return response()->json(['success' => false, 'message' => 'Unrecognized callback event'], 400);
        }

        $data = json_decode($json);
        $merchantRef = $data->merchant_ref;
        $status = $data->status; 

        try {
            if (Str::startsWith($merchantRef, 'INV-EP-')) {
                $purchase = EProductPurchase::where('reference', $merchantRef)->first();
                if (!$purchase) return response()->json(['success' => false, 'message' => 'Purchase not found'], 404);

                if ($status === 'PAID') {
                    $purchase->update(['status' => 'PAID']);
                } elseif (in_array($status, ['EXPIRED', 'FAILED'])) {
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