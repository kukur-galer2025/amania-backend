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
            $apiKey = env('TRIPAY_API_KEY');
            
            if (empty($apiKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'TRIPAY_API_KEY belum dikonfigurasi di file .env backend Anda.'
                ], 400);
            }

            $apiUrl = rtrim(env('TRIPAY_URL', 'https://tripay.co.id/api/'), '/') . '/merchant/payment-channel';

            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey
                ])
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
                'message' => 'Tripay Error: ' . ($result['message'] ?? 'Kredensial API Key tidak valid.'),
                'debug'   => $result
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

        // 1. CEK STATUS LUNAS: Pencegahan ganda jika user sudah bayar.
        $alreadyBought = EProductPurchase::where('user_id', $user->id)
            ->where('e_product_id', $product->id)
            ->whereIn('status', ['PAID', 'success', 'SETTLED'])
            ->exists();

        if ($alreadyBought) {
            return response()->json(['success' => false, 'message' => 'Anda sudah memiliki akses ke produk digital ini.']);
        }

        // 🔥 IDE ANDA DITERAPKAN DI SINI 🔥
        // Kita TIDAK LAGI membatalkan invoice lama menjadi EXPIRED. 
        // Biarkan user membuat banyak invoice UNPAID (Misal: 1 QRIS, 1 VA BCA).

        $merchantRef = 'INV-EP-' . strtoupper(Str::random(8)) . '-' . $user->id;
        $amount = (int) $product->price;

        DB::beginTransaction();
        try {
            // JIKA GRATIS (BYPASS TRIPAY)
            if ($amount == 0) {
                EProductPurchase::create([
                    'reference'    => $merchantRef, 
                    'user_id'      => $user->id,
                    'e_product_id' => $product->id,
                    'amount'       => 0,
                    'status'       => 'PAID',
                ]);
                DB::commit();
                return response()->json([
                    'success'  => true,
                    'message'  => 'Produk gratis berhasil diklaim!',
                    'is_free'  => true,
                ]);
            }

            // ==========================================
            // LOGIKA SIGNATURE & REQUEST TRIPAY
            // ==========================================
            $privateKey   = env('TRIPAY_PRIVATE_KEY');
            $merchantCode = env('TRIPAY_MERCHANT_CODE');
            $apiKey       = env('TRIPAY_API_KEY');

            if (empty($privateKey) || empty($merchantCode) || empty($apiKey)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal: Kredensial Tripay belum lengkap di file .env backend.'
                ], 400);
            }

            $apiUrl = rtrim(env('TRIPAY_URL', 'https://tripay.co.id/api/'), '/') . '/transaction/create';
            $signature = hash_hmac('sha256', $merchantCode . $merchantRef . $amount, $privateKey);

            $payload = [
                'method'         => $request->input('method'),
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
                'return_url'   => rtrim(env('FRONTEND_URL', 'https://amania.id'), '/') . '/e-products/' . $product->slug,
                'expired_time' => (time() + (24 * 60 * 60)), // Expired default 24 Jam
                'signature'    => $signature
            ];

            // KIRIM REQUEST KE TRIPAY
            $response = Http::withoutVerifying()
                ->withHeaders(['Authorization' => 'Bearer ' . $apiKey])
                ->post($apiUrl, $payload);
                
            $result = $response->json();

            // JIKA TRIPAY SUKSES, SIMPAN KE DATABASE
            if ($response->successful() && isset($result['success']) && $result['success'] == true) {
                
                EProductPurchase::create([
                    'reference'        => $merchantRef, 
                    'tripay_reference' => $result['data']['reference'],
                    'user_id'          => $user->id,
                    'e_product_id'     => $product->id,
                    'amount'           => $amount,
                    'checkout_url'     => $result['data']['checkout_url'],
                    'expired_time'     => $result['data']['expired_time'] ?? null,
                    'status'           => 'UNPAID', // Status awal selalu UNPAID
                ]);
                
                DB::commit();

                return response()->json([
                    'success'      => true,
                    'message'      => 'Silakan lakukan pembayaran.',
                    'checkout_url' => $result['data']['checkout_url'],
                ]);
            }

            // Jika Tripay menolak request
            DB::rollBack();
            Log::error('Tripay Create Transaction Error: ', $result ?? []);
            return response()->json([
                'success' => false, 
                'message' => 'Tripay Error: ' . ($result['message'] ?? 'Gagal membuat transaksi ke payment gateway.')
            ], 400);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Checkout System Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Terjadi kesalahan Sistem: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================================================================
    // 3. WEBHOOK KHUSUS E-PRODUCT (CALLBACK TRIPAY)
    // =========================================================================
    public function tripayWebhook(Request $request)
    {
        $callbackSignature = $request->header('X-Callback-Signature') ?? $request->server('HTTP_X_CALLBACK_SIGNATURE');
        $json = $request->getContent();
        
        $signature = hash_hmac('sha256', $json, env('TRIPAY_PRIVATE_KEY'));

        if ($signature !== $callbackSignature) {
            Log::warning('Tripay Webhook: Invalid Signature');
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        $event = $request->header('X-Callback-Event') ?? $request->server('HTTP_X_CALLBACK_EVENT');
        if ('payment_status' !== $event) {
            return response()->json(['success' => false, 'message' => 'Unrecognized callback event'], 400);
        }

        $data = json_decode($json);
        $merchantRef = $data->merchant_ref;
        $status = $data->status;

        try {
            if (Str::startsWith($merchantRef, 'INV-EP-')) {
                $purchase = EProductPurchase::where('reference', $merchantRef)->first();
                
                if (!$purchase) {
                    return response()->json(['success' => false, 'message' => 'Purchase not found'], 404);
                }

                // 🔥 IDE ANDA DITERAPKAN DI SINI (DI DALAM WEBHOOK) 🔥
                if (in_array($status, ['PAID', 'SETTLED'])) {
                    
                    // 1. LUNASKAN TAGIHAN INI
                    $purchase->update(['status' => 'PAID']);

                    // 2. EXPIRED-KAN SEMUA TAGIHAN UNPAID LAINNYA UNTUK USER & PRODUK YANG SAMA
                    EProductPurchase::where('user_id', $purchase->user_id)
                        ->where('e_product_id', $purchase->e_product_id)
                        ->where('id', '!=', $purchase->id) // Kecuali tagihan yang baru saja lunas ini
                        ->where('status', 'UNPAID')
                        ->update(['status' => 'EXPIRED']);

                } elseif (in_array($status, ['EXPIRED', 'FAILED', 'REFUND'])) {
                    $purchase->update(['status' => 'EXPIRED']);
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