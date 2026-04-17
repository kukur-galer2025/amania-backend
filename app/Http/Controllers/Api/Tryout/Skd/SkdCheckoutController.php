<?php

namespace App\Http\Controllers\Api\Tryout\Skd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SkdTryout;
use App\Models\SkdTransaction;
use App\Models\SkdUserTryout; // Tambahkan ini untuk akses tryout gratis
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SkdCheckoutController extends Controller
{
    // =========================================================================
    // 1. MENGAMBIL DAFTAR METODE PEMBAYARAN DARI TRIPAY
    // =========================================================================
    public function getPaymentChannels()
    {
        try {
            // 🔥 PERBAIKAN: Tambahkan withoutVerifying() untuk Laragon localhost
            $response = Http::withoutVerifying()
                ->withToken(env('TRIPAY_API_KEY'))
                ->get(env('TRIPAY_URL') . 'merchant/payment-channel');
            
            if ($response->successful()) {
                $result = $response->json();
                if (isset($result['success']) && $result['success'] === true) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Berhasil mengambil metode pembayaran dari Tripay.',
                        'data'    => $result['data']
                    ], 200);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal terhubung ke Tripay. (HTTP ' . $response->status() . ')',
                'data'    => []
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Tripay Channels Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                // 🔥 PERBAIKAN: Tampilkan pesan error asli agar tahu penyebabnya
                'message' => 'Sistem Error: ' . $e->getMessage(),
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

        // Periksa apakah user sudah pernah beli dan LUNAS
        $alreadyBought = SkdTransaction::where('user_id', $user->id)
            ->where('skd_tryout_id', $tryout->id)
            ->where('status', 'PAID')
            ->exists();

        if ($alreadyBought) {
            return response()->json(['success' => false, 'message' => 'Anda sudah memiliki akses ke tryout ini.']);
        }

        $amount = (int) (($tryout->discount_price > 0 && $tryout->discount_price < $tryout->price) 
                  ? $tryout->discount_price 
                  : $tryout->price);

        // Jika Harga 0 (Gratis), Bypass Tripay
        if ($amount <= 0) {
            // Simpan transaksi lunas
            SkdTransaction::create([
                'user_id'        => $user->id,
                'skd_tryout_id'  => $tryout->id,
                'merchant_ref'   => 'SKD-FREE-' . time() . '-' . $user->id,
                'amount'         => 0,
                'payment_method' => 'FREE',
                'status'         => 'PAID',
                'paid_at'        => now(),
            ]);

            // Berikan Hak Akses (3 kali kesempatan mencoba)
            SkdUserTryout::firstOrCreate(
                ['user_id' => $user->id, 'skd_tryout_id' => $tryout->id],
                ['attempts_left' => 3]
            );

            return response()->json([
                'success' => true, 
                'message' => 'Paket gratis berhasil diklaim!',
                'is_free' => true
            ]);
        }

        // Prefix "SKD-" sangat penting untuk diidentifikasi oleh Webhook
        $merchantRef = 'SKD-' . time() . '-' . $user->id;
        $signature = hash_hmac('sha256', env('TRIPAY_MERCHANT_CODE') . $merchantRef . $amount, env('TRIPAY_PRIVATE_KEY'));

        $payload = [
            'method'         => $request->method,
            'merchant_ref'   => $merchantRef,
            'amount'         => $amount,
            'customer_name'  => $user->name ?? 'Member Amania',
            'customer_email' => $user->email ?? 'email@amania.id',
            'customer_phone' => $user->phone ?? '080000000000',
            'order_items'    => [
                [
                    'sku'      => 'SKD-' . $tryout->id,
                    'name'     => 'Tryout SKD: ' . substr($tryout->title, 0, 30),
                    'price'    => $amount,
                    'quantity' => 1,
                ]
            ],
            'return_url'   => env('FRONTEND_URL', 'http://localhost:3000') . '/tryouts/belajarku',
            'expired_time' => (time() + (24 * 60 * 60)), 
            'signature'    => $signature
        ];

        try {
            // 🔥 PERBAIKAN: Tambahkan withoutVerifying() di sini juga
            $response = Http::withoutVerifying()
                ->withToken(env('TRIPAY_API_KEY'))
                ->post(env('TRIPAY_URL') . 'transaction/create', $payload);
            
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
                    'success'      => true,
                    'message'      => 'Transaksi berhasil dibuat.',
                    'data'         => $transaction,
                    'checkout_url' => $result['data']['checkout_url'] 
                ], 201);
            }

            Log::error('Tripay SKD Error: ', $result ?? []);
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Gagal membuat transaksi dengan Tripay.',
            ], 400);

        } catch (\Exception $e) {
            Log::error('System SKD Checkout Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Kesalahan sistem: ' . $e->getMessage()
            ], 500);
        }
    }

    // =========================================================================
    // 3. MENGAMBIL RIWAYAT TRANSAKSI USER (UNTUK DASHBOARD BELAJARKU)
    // =========================================================================
    public function myTransactions(Request $request)
    {
        $user = $request->user();

        // Pastikan relasi 'tryout' (bukan 'skd_tryout') dan 'category' ada di model SkdTransaction dan SkdTryout
        $transactions = SkdTransaction::with(['tryout.category'])
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