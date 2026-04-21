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
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->latest();

        $products = $query->get();
        $user = auth('sanctum')->user(); // Cek apakah ada user yang sedang login

        // Jika user login, cek produk mana saja yang sudah dibeli
        if ($user) {
            $purchasedProductIds = EProductPurchase::where('user_id', $user->id)
                ->whereIn('status', ['PAID', 'success'])
                ->pluck('e_product_id')
                ->toArray();

            // Tambahkan atribut buatan 'is_purchased'
            $products->map(function ($product) use ($purchasedProductIds) {
                $product->is_purchased = in_array($product->id, $purchasedProductIds);
                return $product;
            });
        } else {
            // Jika tamu, otomatis false semua
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
            ->with(['author:id,name', 'reviews.user:id,name,avatar'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->first();

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan.'], 404);
        }

        // Cek apakah user sedang login dan sudah membeli produk ini
        $user = auth('sanctum')->user();
        if ($user) {
            $isPurchased = EProductPurchase::where('user_id', $user->id)
                ->where('e_product_id', $product->id)
                ->whereIn('status', ['PAID', 'success'])
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

    /**
     * 🔥 MENGAMBIL E-PRODUK YANG SUDAH DIBELI USER (LUNAS) 🔥
     */
    public function myProducts(Request $request)
    {
        $purchases = EProductPurchase::with(['product', 'product.author'])
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['PAID', 'success'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $purchases
        ]);
    }
}