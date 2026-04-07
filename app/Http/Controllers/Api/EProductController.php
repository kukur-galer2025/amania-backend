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
     * 🔥 TAMPILKAN SEMUA E-PRODUK DI KATALOG (PUBLIK) 🔥
     */
    public function index()
    {
        // Ambil produk yang di-publish, sertakan rata-rata rating dan jumlah ulasan
        $products = EProduct::where('is_published', true)
            ->withAvg('reviews', 'rating') // Menghitung rata-rata bintang otomatis
            ->withCount('reviews')         // Menghitung total orang yang mereview
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
            ->with(['author:id,name', 'reviews.user:id,name,avatar']) // Bawa data ulasan beserta nama user-nya
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
     * 🔥 SUBMIT ULASAN (KHUSUS MEMBER YANG SUDAH BELI) 🔥
     */
    public function submitReview(Request $request, $id)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000'
        ]);

        $user = $request->user();

        // 1. Validasi ketat: Pastikan user SUDAH PERNAH BELI dan statusnya SUCCESS
        $hasPurchased = EProductPurchase::where('user_id', $user->id)
            ->where('e_product_id', $id)
            ->where('status', 'success')
            ->exists();

        if (!$hasPurchased) {
            return response()->json([
                'success' => false, 
                'message' => 'Kamu harus membeli dan menyelesaikan pembayaran produk ini terlebih dahulu untuk memberikan ulasan.'
            ], 403);
        }

        // 2. Simpan Ulasan (Gunakan updateOrCreate agar jika dia edit ulasan, tidak jadi double)
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