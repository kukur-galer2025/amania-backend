<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EProduct;
use App\Models\EProductPurchase;
use App\Models\EProductReview;
use Illuminate\Http\Request;

class EProductController extends Controller
{
    /**
     * 🔥 TAMPILKAN SEMUA E-PRODUK DI KATALOG (PUBLIK & MEMBER) 🔥
     */
    public function index(Request $request)
    {
        $query = EProduct::where('is_published', true)
            ->with(['author:id,name', 'category:id,name']) 
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->latest();

        $products = $query->get();
        $user = auth('sanctum')->user();

        if ($user) {
            $purchasedProductIds = EProductPurchase::where('user_id', $user->id)
                ->whereIn('status', ['PAID', 'success', 'SETTLED']) // Ditambah SETTLED
                ->pluck('e_product_id')
                ->toArray();

            $products->map(function ($product) use ($purchasedProductIds) {
                $product->is_purchased = in_array($product->id, $purchasedProductIds);
                return $product;
            });
        } else {
            $products->map(function ($product) {
                $product->is_purchased = false;
                return $product;
            });
        }

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
            ->with(['author:id,name', 'reviews.user:id,name,avatar', 'category:id,name'])
            ->withCount('materials') // Hitung jumlah file/materi yang ada
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->first();

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan.'], 404);
        }

        $user = auth('sanctum')->user();
        if ($user) {
            $isPurchased = EProductPurchase::where('user_id', $user->id)
                ->where('e_product_id', $product->id)
                ->whereIn('status', ['PAID', 'success', 'SETTLED'])
                ->exists();
            $product->is_purchased = $isPurchased;
        } else {
            $product->is_purchased = false;
        }

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
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

        $hasPurchased = EProductPurchase::where('user_id', $user->id)
            ->where('e_product_id', $id)
            ->whereIn('status', ['PAID', 'success', 'SETTLED'])
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

    /**
     * 🔥 MENGAMBIL E-PRODUK YANG SUDAH DIBELI USER (LUNAS) 🔥
     * Digunakan untuk halaman koleksi belajar
     */
    public function myProducts(Request $request)
    {
        $purchases = EProductPurchase::with([
            'product.category:id,name',
            'product.author:id,name', 
            'product.materials' // Load materi agar bisa diakses oleh Member
        ])
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['PAID', 'success', 'SETTLED'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $purchases
        ]);
    }

    /**
     * 🔥 MENGAMBIL SEMUA RIWAYAT TRANSAKSI E-PRODUK (PAID & UNPAID) 🔥
     * Digunakan untuk halaman Riwayat Transaksi agar user bisa lanjut bayar
     */
    public function myTransactions(Request $request)
    {
        $transactions = EProductPurchase::with(['product', 'product.author:id,name', 'product.category:id,name'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }

    /**
     * 🔥 DETAIL E-PRODUK MEMBER (RUANG KELAS / COURSE VIEWER) 🔥
     * API baru untuk halaman /my-e-products/[slug]
     */
    public function myProductDetail(Request $request, $slug)
    {
        // Cari data transaksi user yang statusnya lunas untuk slug produk ini
        $purchase = EProductPurchase::with([
            'product.category:id,name',
            'product.author:id,name',
            'product.materials' // Load semua materi
        ])
        ->where('user_id', $request->user()->id)
        ->whereIn('status', ['PAID', 'success', 'SETTLED'])
        ->whereHas('product', function ($query) use ($slug) {
            $query->where('slug', $slug);
        })
        ->first();

        // Jika tidak ketemu atau belum beli, tolak akses
        if (!$purchase || !$purchase->product) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak. Anda belum memiliki produk ini.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $purchase->product
        ]);
    }
}