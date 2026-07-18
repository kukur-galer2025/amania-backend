<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EProduct;
use App\Models\EProductPurchase;
use App\Models\EProductOrderItem;
use App\Models\Cart;
use App\Models\Course;
use App\Models\CourseEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Helpers\ImageHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EProductCheckoutController extends Controller
{
    // =========================================================================
    // 1. MENGAMBIL DAFTAR METODE PEMBAYARAN DARI TRIPAY (TIDAK DIPAKAI LAGI)
    // =========================================================================
    public function getPaymentChannels()
    {
        return response()->json([
            'success' => true,
            'message' => 'Menggunakan pembayaran manual QRIS',
            'data'    => []
        ], 200);
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
            // LOGIKA PEMBAYARAN MANUAL QRIS (UPLOAD)
            // ==========================================
            if (!$request->hasFile('payment_proof')) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Bukti pembayaran wajib diunggah.'], 400);
            }

            $paymentPath = ImageHelper::compressAndStore($request->file('payment_proof'), 'payments', 1200, 1600, 80);

            $purchase = EProductPurchase::create([
                'reference'        => $merchantRef, 
                'user_id'          => $user->id,
                'amount'           => $totalAmount,
                'payment_method'   => 'MANUAL_QRIS', 
                'payment_proof'    => $paymentPath,
                'status'           => 'PAID', // 🔥 AUTO-APPROVE: Langsung PAID, Admin cek mutasi belakangan
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
                'message'      => 'Pembayaran berhasil! Akses produk Anda telah aktif.',
            ]);

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
    // 3. CHECKOUT KURSUS ONLINE VIA TRIPAY
    // =========================================================================
    public function purchaseCourse(Request $request)
    {
        $request->validate([
            'course_id'     => 'required|exists:courses,id',
            'payment_proof' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $user   = $request->user();
        $course = Course::where('is_published', true)->findOrFail($request->course_id);

        // Cek apakah sudah enrolled
        $alreadyEnrolled = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereIn('status', ['PAID', 'success', 'SETTLED'])
            ->exists();

        if ($alreadyEnrolled) {
            return response()->json(['success' => false, 'message' => 'Anda sudah memiliki akses ke kursus ini.']);
        }

        $totalAmount = (int) $course->price;
        $merchantRef = 'INV-CRS-' . strtoupper(Str::random(8)) . '-' . $user->id;

        DB::beginTransaction();
        try {
            // JIKA GRATIS
            if ($totalAmount == 0) {
                CourseEnrollment::create([
                    'reference'      => $merchantRef,
                    'user_id'        => $user->id,
                    'course_id'      => $course->id,
                    'amount'         => 0,
                    'payment_method' => 'FREE_CLAIM',
                    'status'         => 'PAID',
                ]);

                DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'Kursus gratis berhasil diklaim!',
                    'is_free' => true,
                ]);
            }

            // ==========================================
            // LOGIKA PEMBAYARAN MANUAL QRIS (UPLOAD)
            // ==========================================
            if (!$request->hasFile('payment_proof')) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Bukti pembayaran wajib diunggah.'], 400);
            }

            $paymentPath = ImageHelper::compressAndStore($request->file('payment_proof'), 'payments', 1200, 1600, 80);

            CourseEnrollment::create([
                'reference'        => $merchantRef,
                'user_id'          => $user->id,
                'course_id'        => $course->id,
                'amount'           => $totalAmount,
                'payment_method'   => 'MANUAL_QRIS',
                'payment_proof'    => $paymentPath,
                'status'           => 'PAID', // 🔥 AUTO-APPROVE: Langsung PAID, Admin cek mutasi belakangan
            ]);

            DB::commit();
            return response()->json([
                'success'      => true,
                'message'      => 'Pembayaran berhasil! Akses kursus Anda telah aktif.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Course Checkout Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // 4. WEBHOOK CALLBACK TRIPAY (E-PRODUCT + KURSUS)
    // =========================================================================
    public function tripayWebhook(Request $request)
    {
        return response()->json(['success' => false, 'message' => 'Tripay Webhook is deprecated.']);
    }

    // =========================================================================
    // 5. UPLOAD ULANG BUKTI PEMBAYARAN
    // =========================================================================
    public function reuploadPaymentProofEProduct(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Silakan login terlebih dahulu.'], 401);
        }

        $request->validate([
            'payment_proof' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120'
        ]);
        
        $transaction = EProductPurchase::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        if (!in_array($transaction->status, ['FAILED', 'REJECTED'])) {
            return response()->json([
                'success' => false, 
                'message' => 'Hanya transaksi yang ditolak/gagal yang dapat mengunggah ulang bukti.'
            ], 400);
        }

        $paymentPath = ImageHelper::compressAndStore($request->file('payment_proof'), 'payments', 1200, 1600, 80);

        $transaction->update([
            'payment_proof' => $paymentPath,
            'status' => 'PAID', // 🔥 AUTO-APPROVE: Langsung PAID
            'rejection_reason' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bukti berhasil diunggah ulang! Akses produk Anda telah aktif kembali.',
            'data' => $transaction
        ]);
    }

    public function reuploadPaymentProofCourse(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Silakan login terlebih dahulu.'], 401);
        }

        $request->validate([
            'payment_proof' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120'
        ]);
        
        $transaction = CourseEnrollment::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        if (!in_array($transaction->status, ['FAILED', 'REJECTED'])) {
            return response()->json([
                'success' => false, 
                'message' => 'Hanya transaksi yang ditolak/gagal yang dapat mengunggah ulang bukti.'
            ], 400);
        }

        $paymentPath = ImageHelper::compressAndStore($request->file('payment_proof'), 'payments', 1200, 1600, 80);

        $transaction->update([
            'payment_proof' => $paymentPath,
            'status' => 'PAID', // 🔥 AUTO-APPROVE: Langsung PAID
            'rejection_reason' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bukti berhasil diunggah ulang! Akses kursus Anda telah aktif kembali.',
            'data' => $transaction
        ]);
    }
}