<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EProduct;
use App\Models\EProductPurchase;
use App\Models\EProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class EProductController extends Controller
{
    /**
     * 🔥 TAMPILKAN SEMUA E-PRODUK DI KATALOG (PUBLIK) 🔥
     */
    public function index()
    {
        $products = EProduct::where('is_published', true)
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * 🔥 DETAIL E-PRODUK (PUBLIK) 🔥
     */
    public function show($slug)
    {
        $product = EProduct::where('slug', $slug)
            ->where('is_published', true)
            ->with(['author:id,name', 'reviews.user:id,name,avatar'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->first();

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    /**
     * 🔥 CHECKOUT PEMBELIAN E-PRODUK MENGGUNAKAN TRIPAY 🔥
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'e_product_id' => 'required|exists:e_products,id',
            'method'       => 'nullable|string' 
        ]);

        $user = $request->user();
        $product = EProduct::findOrFail($request->e_product_id);
        
        // 1. Cek apakah user sudah pernah beli dan statusnya Lunas (PAID / success)
        $hasPurchased = EProductPurchase::where('user_id', $user->id)
            ->where('e_product_id', $product->id)
            ->whereIn('status', ['PAID', 'success'])
            ->exists();

        if ($hasPurchased) {
            return response()->json(['success' => false, 'message' => 'Anda sudah memiliki akses ke produk digital ini.']);
        }

        // 2. Jika Produk GRATIS (Bypass Tripay)
        if ($product->price == 0) {
            EProductPurchase::create([
                'user_id'      => $user->id,
                'e_product_id' => $product->id,
                'amount'       => 0,
                'status'       => 'PAID', // Langsung Aktif
                'reference'    => 'FREE-' . time() . '-' . $user->id,
                'tripay_reference' => null,
                'checkout_url' => null
            ]);
            return response()->json(['success' => true, 'is_free' => true]);
        }

        // 3. SETUP TRIPAY MENGGUNAKAN CONFIG (MENCEGAH ERROR ENV)
        $apiKey       = config('tripay.api_key');
        $privateKey   = config('tripay.private_key');
        $merchantCode = config('tripay.merchant_code');
        $apiUrl       = config('tripay.api_url') . 'transaction/create';
        
        $merchantRef  = 'EPRD-' . strtoupper(Str::random(8)) . '-' . $user->id;
        $amount       = (int) $product->price;
        $paymentMethod = $request->method ?? 'QRIS'; // Default ke QRIS

        // Membuat Signature Tripay
        $signature = hash_hmac('sha256', $merchantCode.$merchantRef.$amount, $privateKey);

        $payload = [
            'method'         => $paymentMethod,
            'merchant_ref'   => $merchantRef,
            'amount'         => $amount,
            'customer_name'  => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone ?? '081234567890',
            'order_items'    => [
                [
                    'sku'         => 'EPRD-' . $product->id,
                    'name'        => substr($product->title, 0, 50),
                    'price'       => $amount,
                    'quantity'    => 1,
                ]
            ],
            // Return URL agar user otomatis kembali ke halaman web setelah bayar
            'return_url'   => config('app.frontend_url', 'http://localhost:3000') . '/e-products/' . $product->slug,
            'expired_time' => (time() + (24 * 60 * 60)), // Expired dalam 24 Jam
            'signature'    => $signature
        ];

        try {
            // Eksekusi HTTP Request ke Tripay
            $response = Http::withToken($apiKey)->post($apiUrl, $payload);
            $result = $response->json();

            if ($response->successful() && isset($result['success']) && $result['success'] == true) {
                
                // 4. Simpan Data Transaksi Pending ke Database sesuai Migration Tripay
                EProductPurchase::create([
                    'reference'        => $merchantRef,
                    'tripay_reference' => $result['data']['reference'],
                    'user_id'          => $user->id,
                    'e_product_id'     => $product->id,
                    'amount'           => $amount,
                    'status'           => 'UNPAID', // Status awal belum dibayar
                    'checkout_url'     => $result['data']['checkout_url']
                ]);

                return response()->json([
                    'success'      => true,
                    'checkout_url' => $result['data']['checkout_url'] 
                ]);
            }

            Log::error('Tripay API Error: ', $result ?? []);
            return response()->json(['success' => false, 'message' => $result['message'] ?? 'Gagal memproses pembayaran Tripay. Pastikan metode pembayaran benar.']);

        } catch (\Exception $e) {
            Log::error('Tripay HTTP Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal terhubung ke server pembayaran Tripay.']);
        }
    }

    /**
     * 🔥 SUBMIT ULASAN (KHUSUS MEMBER YANG SUDAH BELI) 🔥
     */
    public function submitReview(Request $request, $id)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000'
        ]);

        $user = $request->user();

        // Validasi ketat: Pastikan user SUDAH PERNAH BELI dan statusnya PAID / success
        $hasPurchased = EProductPurchase::where('user_id', $user->id)
            ->where('e_product_id', $id)
            ->whereIn('status', ['PAID', 'success'])
            ->exists();

        if (!$hasPurchased) {
            return response()->json([
                'success' => false, 
                'message' => 'Kamu harus membeli dan menyelesaikan pembayaran produk ini terlebih dahulu untuk memberikan ulasan.'
            ], 403);
        }

        $review = EProductReview::updateOrCreate(
            [
                'e_product_id' => $id,
                'user_id' => $user->id
            ],
            [
                'rating' => $request->rating,
                'review' => $request->review
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Terima kasih! Ulasan kamu berhasil disimpan.',
            'data' => $review
        ]);
    }
}