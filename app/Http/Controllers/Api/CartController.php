<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\EProductPurchase;
use Illuminate\Http\Request;

class CartController extends Controller
{
    // 1. Tampilkan isi keranjang
    public function index(Request $request)
    {
        // 🔥 Relasi diperbaiki agar tidak crash mencari kolom original_price 🔥
        $carts = Cart::with(['product.category'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        $totalPrice = $carts->sum(function($cart) {
            return $cart->product ? $cart->product->price : 0;
        });

        return response()->json([
            'success' => true,
            'data' => $carts,
            'summary' => [
                'total_items' => $carts->count(),
                'total_price' => $totalPrice
            ]
        ]);
    }

    // 2. Tambah produk ke keranjang
    public function store(Request $request)
    {
        $request->validate([
            'e_product_id' => 'required|exists:e_products,id'
        ]);

        $userId = $request->user()->id;
        $productId = $request->e_product_id;

        // 🔥 HANYA MENGGUNAKAN RELASI ITEMS (SUDAH AMAN UNTUK MIGRATION & APRIORI) 🔥
        $alreadyBought = EProductPurchase::where('user_id', $userId)
            ->whereHas('items', function($q) use ($productId) {
                $q->where('e_product_id', $productId);
            })
            ->whereIn('status', ['PAID', 'success', 'SETTLED'])
            ->exists();

        if ($alreadyBought) {
            return response()->json([
                'success' => false, 
                'message' => 'Anda sudah memiliki akses ke produk digital ini.'
            ], 200); 
        }

        // Cek 2: Apakah produk sudah ada di keranjang yang belum di-checkout?
        $alreadyInCart = Cart::where('user_id', $userId)
            ->where('e_product_id', $productId)
            ->exists();

        if ($alreadyInCart) {
            return response()->json([
                'success' => false, 
                'message' => 'Produk ini sudah ada di dalam Keranjang Anda.'
            ], 200); 
        }

        // Lolos semua validasi, masukkan ke keranjang
        $cart = Cart::create([
            'user_id' => $userId,
            'e_product_id' => $productId
        ]);

        return response()->json([
            'success' => true, 
            'message' => 'Berhasil ditambahkan ke keranjang!', 
            'data' => $cart
        ]);
    }

    // 3. Hapus produk dari keranjang
    public function destroy(Request $request, $id)
    {
        $cart = Cart::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$cart) {
            return response()->json([
                'success' => false, 
                'message' => 'Item keranjang tidak ditemukan.'
            ], 404);
        }

        $cart->delete();

        return response()->json([
            'success' => true, 
            'message' => 'Produk dihapus dari keranjang.'
        ]);
    }
}