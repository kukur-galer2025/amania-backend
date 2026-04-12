<?php

namespace App\Http\Controllers\Api\Tryout\Skd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SkdTryout;
use App\Models\SkdTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SkdCheckoutController extends Controller
{
    // =========================================================================
    // 1. MENGAMBIL DAFTAR METODE PEMBAYARAN DARI TRIPAY
    // =========================================================================
    public function getPaymentChannels()
    {
        $apiKey = config('tripay.api_key');
        $url = config('tripay.api_url') . 'merchant/payment-channel';

        try {
            $response = Http::withToken($apiKey)->get($url);
            
            if ($response->successful()) {
                $result = $response->json();
                if (isset($result['success']) && $result['success'] === true) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Berhasil mengambil metode pembayaran dari Tripay.',
                        'data'    => $result['data']
                    ], 200);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Gagal dari Tripay: ' . ($result['message'] ?? 'Unknown Error'),
                        'data'    => []
                    ], 400);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal terhubung ke Tripay. (HTTP ' . $response->status() . ')',
                'data'    => []
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan internal server.',
                'data'    => []
            ], 500);
        }
    }

    // =========================================================================
    // 2. MEMBUAT TRANSAKSI PEMBAYARAN SKD (CHECKOUT)
    // =========================================================================
    public function createTransaction(Request $request)
    {
        $request->validate([
            'skd_tryout_id' => 'required|exists:skd_tryouts,id',
            'method'        => 'required|string', 
        ]);

        $user = $request->user();
        $tryout = SkdTryout::findOrFail($request->skd_tryout_id);

        $amount = (int) (($tryout->discount_price > 0 && $tryout->discount_price < $tryout->price) 
                  ? $tryout->discount_price 
                  : $tryout->price);

        if ($amount <= 0) {
            return response()->json(['success' => false, 'message' => 'Paket gratis, gunakan endpoint klaim gratis.'], 400);
        }

        // Prefix "SKD-" sangat penting untuk diidentifikasi oleh Webhook nanti
        $merchantRef = 'SKD-' . time() . '-' . $user->id;

        $privateKey = config('tripay.private_key');
        $merchantCode = config('tripay.merchant_code');
        $signature = hash_hmac('sha256', $merchantCode . $merchantRef . $amount, $privateKey);

        $payload = [
            'method'         => $request->method,
            'merchant_ref'   => $merchantRef,
            'amount'         => $amount,
            'customer_name'  => $user->name ?? 'Member Amania',
            'customer_email' => $user->email ?? 'email@amania.id',
            'customer_phone' => $user->phone ?? '081234567890',
            'order_items'    => [
                [
                    'sku'      => 'SKD-' . $tryout->id,
                    'name'     => 'Tryout SKD: ' . $tryout->title,
                    'price'    => $amount,
                    'quantity' => 1,
                ]
            ],
            'return_url'   => config('app.frontend_url') . '/tryouts/belajarku',
            'expired_time' => (time() + (24 * 60 * 60)), 
            'signature'    => $signature
        ];

        try {
            $apiKey = config('tripay.api_key');
            $url = config('tripay.api_url') . 'transaction/create';

            $response = Http::withToken($apiKey)->post($url, $payload);
            $result = $response->json();
            
            if ($response->successful() && isset($result['success']) && $result['success'] == true) {
                $transaction = SkdTransaction::create([
                    'user_id'        => $user->id,
                    'skd_tryout_id'  => $tryout->id,
                    'reference'      => $result['data']['reference'], 
                    'merchant_ref'   => $merchantRef,
                    'amount'         => $amount,
                    'payment_method' => $request->method,
                    'payment_name'   => $result['data']['payment_name'],
                    'checkout_url'   => $result['data']['checkout_url'],
                    'status'         => 'UNPAID',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Transaksi berhasil dibuat.',
                    'data'    => $transaction
                ], 201);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Gagal membuat transaksi dengan Tripay.',
            ], 400);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Kesalahan sistem saat memproses transaksi.'], 500);
        }
    }

    // =========================================================================
    // 3. MENGAMBIL RIWAYAT TRANSAKSI USER (UNTUK DASHBOARD BELAJARKU)
    // =========================================================================
    public function myTransactions(Request $request)
    {
        $user = $request->user();

        // Ambil semua transaksi milik user ini beserta relasi tryout dan kategori tryout-nya
        $transactions = SkdTransaction::with(['skd_tryout.category'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil data transaksi.',
            'data'    => $transactions
        ]);
    }
}