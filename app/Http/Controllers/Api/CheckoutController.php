<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EProduct;
use App\Models\EProductPurchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function __construct()
    {
        \Midtrans\Config::$serverKey    = env('MIDTRANS_SERVER_KEY');
        \Midtrans\Config::$isProduction = false;
        \Midtrans\Config::$isSanitized  = true;
        \Midtrans\Config::$is3ds        = true;
    }

    /**
     * Fungsi Checkout Member
     */
    public function purchaseEProduct(Request $request)
    {
        $request->validate(['e_product_id' => 'required|exists:e_products,id']);

        $user    = $request->user();
        $product = EProduct::where('is_published', true)->findOrFail($request->e_product_id);

        // 1. Cek apakah user sudah pernah beli dan statusnya success
        $alreadyBought = EProductPurchase::where('user_id', $user->id)
            ->where('e_product_id', $product->id)
            ->where('status', 'success')
            ->exists();

        if ($alreadyBought) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah memiliki produk ini.'
            ], 400);
        }

        $invoiceCode = 'INV-EP-' . strtoupper(Str::random(8));

        DB::beginTransaction();
        try {
            // 2. Buat Data Transaksi di Database
            $purchase = EProductPurchase::create([
                'invoice_code' => $invoiceCode,
                'user_id'      => $user->id,
                'e_product_id' => $product->id,
                'amount'       => $product->price,
                'status'       => 'pending',
            ]);

            // 3. Jika Produk Gratis, Langsung Success
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

            // 4. Jika Berbayar, Tembak API Midtrans — HANYA SEKALI
            $params = [
                'transaction_details' => [
                    'order_id'     => $invoiceCode,
                    'gross_amount' => (int) $product->price, // pastikan integer
                ],
                'item_details' => [[
                    'id'       => $product->id,
                    'price'    => (int) $product->price,
                    'quantity' => 1,
                    'name'     => substr($product->title, 0, 50),
                ]],
                'customer_details' => [
                    'first_name' => substr($user->name, 0, 20),
                    'email'      => $user->email,
                ],
            ];

            // ✅ PERBAIKAN UTAMA: Panggil createTransaction SEKALI SAJA
            // Ambil token dan redirect_url dari objek yang sama
            $transaction = \Midtrans\Snap::createTransaction($params);
            $snapToken   = $transaction->token;
            $paymentUrl  = $transaction->redirect_url;

            $purchase->update([
                'snap_token'  => $snapToken,
                'payment_url' => $paymentUrl,
            ]);

            DB::commit();

            return response()->json([
                'success'     => true,
                'message'     => 'Silakan lakukan pembayaran.',
                'snap_token'  => $snapToken,
                'payment_url' => $paymentUrl,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan gateway.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fungsi Webhook (Pendengar Laporan Midtrans)
     * Route ini HARUS bisa diakses tanpa login (Public)
     */
    public function webhook(Request $request)
    {
        $serverKey = env('MIDTRANS_SERVER_KEY');

        // 1. Validasi Keamanan (Signature Key) dari Midtrans
        $hashed = hash('sha512',
            $request->order_id .
            $request->status_code .
            $request->gross_amount .
            $serverKey
        );

        if ($hashed !== $request->signature_key) {
            return response()->json(['message' => 'Akses ditolak. Signature tidak valid.'], 403);
        }

        // 2. Cari Transaksi
        $purchase = EProductPurchase::where('invoice_code', $request->order_id)->first();
        if (!$purchase) {
            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }

        // 3. Update Status Sesuai Laporan Midtrans
        $transactionStatus = $request->transaction_status;

        if (in_array($transactionStatus, ['capture', 'settlement'])) {
            $purchase->update(['status' => 'success']);
        } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
            $purchase->update(['status' => 'failed']);
        }

        return response()->json(['message' => 'Status transaksi berhasil diupdate.']);
    }
}
