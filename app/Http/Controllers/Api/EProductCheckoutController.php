<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EProduct;
use App\Models\EProductPurchase;
use App\Models\EProductOrderItem;
use App\Models\Cart;
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
    // 2. FUNGSI CHECKOUT (DUKUNG "BELI LANGSUNG" & "CHECKOUT KERANJANG")
    // =========================================================================
    public function purchaseEProduct(Request $request)
    {
        $request->validate([
            'method'       => 'required|string', 
            'e_product_id' => 'nullable|exists:e_products,id' 
        ]);

        $user = $request->user();
        $isDirectBuy = $request->has('e_product_id') && !empty($request->e_product_id);

        $totalAmount = 0;
        $orderItemsPayload = [];
        $itemsToSave = []; 

        // ---------------------------------------------------------
        // SKENARIO A: BELI LANGSUNG (Direct Buy)
        // ---------------------------------------------------------
        if ($isDirectBuy) {
            $product = EProduct::where('is_published', true)->findOrFail($request->e_product_id);

            // 🔥 HANYA CEK TABEL ORDER ITEMS (SIAP APRIORI) 🔥
            $alreadyBought = EProductPurchase::where('user_id', $user->id)
                ->whereHas('items', function($q) use ($product) {
                    $q->where('e_product_id', $product->id);
                })->whereIn('status', ['PAID', 'success', 'SETTLED'])->exists();

            if ($alreadyBought) {
                return response()->json(['success' => false, 'message' => 'Anda sudah memiliki akses ke produk ini.']);
            }

            $totalAmount = (int) $product->price;
            $orderItemsPayload[] = [
                'sku'      => 'EP-' . $product->id,
                'name'     => substr($product->title, 0, 50),
                'price'    => (int) $product->price,
                'quantity' => 1,
            ];
            $itemsToSave[] = ['id' => $product->id, 'price' => $product->price];
        } 
        // ---------------------------------------------------------
        // SKENARIO B: CHECKOUT DARI KERANJANG (Cart Checkout)
        // ---------------------------------------------------------
        else {
            $carts = Cart::where('user_id', $user->id)->with('product')->get();
            
            if ($carts->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Keranjang belanja Anda kosong.']);
            }

            foreach ($carts as $cart) {
                // 🔥 HANYA CEK TABEL ORDER ITEMS (SIAP APRIORI) 🔥
                $alreadyBought = EProductPurchase::where('user_id', $user->id)
                    ->whereHas('items', function($q) use ($cart) {
                        $q->where('e_product_id', $cart->e_product_id);
                    })->whereIn('status', ['PAID', 'success', 'SETTLED'])->exists();

                if ($alreadyBought) {
                    return response()->json([
                        'success' => false, 
                        'message' => 'Produk "'.$cart->product->title.'" sudah Anda miliki. Silakan hapus dari keranjang.'
                    ], 400);
                }

                $totalAmount += (int) $cart->product->price;
                $orderItemsPayload[] = [
                    'sku'      => 'EP-' . $cart->product->id,
                    'name'     => substr($cart->product->title, 0, 50),
                    'price'    => (int) $cart->product->price,
                    'quantity' => 1,
                ];
                $itemsToSave[] = ['id' => $cart->product->id, 'price' => $cart->product->price];
            }
        }

        $merchantRef = 'INV-EP-' . strtoupper(Str::random(8)) . '-' . $user->id;

        DB::beginTransaction();
        try {
            // ==========================================
            // JIKA TOTAL GRATIS (BYPASS TRIPAY)
            // ==========================================
            if ($totalAmount == 0) {
                $purchase = EProductPurchase::create([
                    'reference'      => $merchantRef, 
                    'user_id'        => $user->id,
                    // 'e_product_id' => ... 🔥 BARIS INI SUDAH DIHAPUS PERMANEN
                    'amount'         => 0,
                    'payment_method' => 'FREE_CLAIM',
                    'status'         => 'PAID',
                ]);

                foreach ($itemsToSave as $item) {
                    EProductOrderItem::create([
                        'e_product_purchase_id' => $purchase->id,
                        'e_product_id'          => $item['id'],
                        'price'                 => 0
                    ]);
                }

                if (!$isDirectBuy) {
                    Cart::where('user_id', $user->id)->delete();
                }

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
                return response()->json(['success' => false, 'message' => 'Gagal: Kredensial Tripay belum lengkap di file .env.'], 400);
            }

            $apiUrl = rtrim(env('TRIPAY_URL', 'https://tripay.co.id/api/'), '/') . '/transaction/create';
            $signature = hash_hmac('sha256', $merchantCode . $merchantRef . $totalAmount, $privateKey);

            $payload = [
                'method'         => $request->input('method'),
                'merchant_ref'   => $merchantRef,
                'amount'         => $totalAmount,
                'customer_name'  => $user->name ?? 'Member Amania',
                'customer_email' => $user->email ?? 'email@amania.id',
                'customer_phone' => $user->phone ?? '08000000000',
                'order_items'    => $orderItemsPayload,
                'return_url'     => rtrim(env('FRONTEND_URL', 'https://amania.id'), '/') . '/my-e-products',
                'expired_time'   => (time() + (24 * 60 * 60)), // Expired default 24 Jam
                'signature'      => $signature
            ];

            // KIRIM REQUEST KE TRIPAY
            $response = Http::withoutVerifying()
                ->withHeaders(['Authorization' => 'Bearer ' . $apiKey])
                ->post($apiUrl, $payload);
                
            $result = $response->json();

            // JIKA TRIPAY SUKSES, SIMPAN KE DATABASE
            if ($response->successful() && isset($result['success']) && $result['success'] == true) {
                
                $purchase = EProductPurchase::create([
                    'reference'        => $merchantRef, 
                    'tripay_reference' => $result['data']['reference'],
                    'user_id'          => $user->id,
                    // 'e_product_id' => ... 🔥 BARIS INI SUDAH DIHAPUS PERMANEN
                    'amount'           => $totalAmount,
                    'checkout_url'     => $result['data']['checkout_url'],
                    'expired_time'     => $result['data']['expired_time'] ?? null,
                    'payment_method'   => $request->input('method'), 
                    'status'           => 'UNPAID',
                ]);

                foreach ($itemsToSave as $item) {
                    EProductOrderItem::create([
                        'e_product_purchase_id' => $purchase->id,
                        'e_product_id'          => $item['id'],
                        'price'                 => $item['price']
                    ]);
                }

                if (!$isDirectBuy) {
                    Cart::where('user_id', $user->id)->delete();
                }
                
                DB::commit();

                return response()->json([
                    'success'      => true,
                    'message'      => 'Pesanan berhasil dibuat! Silakan lakukan pembayaran.',
                    'checkout_url' => $result['data']['checkout_url'],
                ]);
            }

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

                if (in_array($status, ['PAID', 'SETTLED'])) {
                    
                    $updateData = ['status' => 'PAID'];
                    
                    if (isset($data->payment_method)) {
                        $updateData['payment_method'] = $data->payment_method;
                    }
                    
                    $purchase->update($updateData);

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