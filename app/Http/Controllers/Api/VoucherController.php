<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VoucherController extends Controller
{
    /**
     * Dapatkan daftar voucher yang sedang aktif untuk member / publik.
     */
    public function available(Request $request)
    {
        $type = $request->query('type'); // 'e_product' | 'course' | 'event' | null

        $query = Voucher::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>=', now())
                  ->orWhereDate('expires_at', '>=', now()->toDateString());
            })
            ->where(function ($q) {
                $q->whereNull('max_uses')
                  ->orWhereRaw('used_count < max_uses');
            });

        if ($type && in_array($type, ['e_product', 'course', 'event'])) {
            $query->where(function ($q) use ($type) {
                $q->whereIn('applicable_to', ['all', $type])
                  ->orWhereNull('applicable_to');
            });
        }

        $vouchers = $query->orderBy('discount_value', 'desc')
            ->select([
                'id', 'code', 'name', 'discount_type',
                'discount_value', 'min_purchase', 'applicable_to', 'expires_at'
            ])
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $vouchers,
        ]);
    }

    /**
     * Validasi kode voucher dan hitung diskon untuk member.
     *
     * Request:
     *   - code   : string (required)
     *   - amount : integer (required) — harga asli sebelum diskon
     *   - type   : string (required) — 'e_product', 'course', atau 'event'
     */
    public function validate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code'   => 'required|string',
            'amount' => 'required|numeric|min:0',
            'type'   => 'required|in:e_product,course,event',
        ], [
            'code.required'   => 'Silakan masukkan kode voucher.',
            'amount.required' => 'Nominal pembelian tidak valid.',
            'type.required'   => 'Tipe produk tidak valid.',
            'type.in'         => 'Tipe produk harus salah satu dari: e_product, course, event.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 200);
        }

        $code   = strtoupper(trim($request->code));
        $amount = (int) round($request->amount);
        $type   = $request->type;

        $cleanCode = str_replace(' ', '', $code);
        $voucher = Voucher::where('code', $code)
            ->orWhereRaw("REPLACE(code, ' ', '') = ?", [$cleanCode])
            ->first();

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => "Kode voucher '{$code}' tidak ditemukan.",
            ], 200);
        }

        if (!$voucher->is_active) {
            return response()->json([
                'success' => false,
                'message' => "Voucher '{$voucher->code}' sedang tidak aktif.",
            ], 200);
        }

        if ($voucher->expires_at !== null) {
            $expiry = $voucher->expires_at->copy();
            // Jika jam 00:00:00 atau tanggalnya hari ini, beri toleransi hingga akhir hari ini (23:59:59)
            if ($expiry->format('H:i:s') === '00:00:00' || $expiry->isToday()) {
                $expiry = $expiry->endOfDay();
            }
            if ($expiry->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => "Voucher '{$voucher->code}' sudah kedaluwarsa pada " . $voucher->expires_at->format('d M Y') . ".",
                ], 200);
            }
        }

        if ($voucher->max_uses !== null && $voucher->used_count >= $voucher->max_uses) {
            return response()->json([
                'success' => false,
                'message' => "Kuota pemakaian voucher '{$voucher->code}' sudah habis ({$voucher->used_count}/{$voucher->max_uses}).",
            ], 200);
        }

        // Cek applicable_to
        if ($voucher->applicable_to && $voucher->applicable_to !== 'all' && $voucher->applicable_to !== $type) {
            $typeNames = [
                'e_product' => 'E-Product',
                'course'    => 'Kursus Online',
                'event'     => 'Event / Program',
            ];
            $applicableName = $typeNames[$voucher->applicable_to] ?? $voucher->applicable_to;
            $currentName    = $typeNames[$type] ?? $type;
            return response()->json([
                'success' => false,
                'message' => "Voucher ini khusus untuk {$applicableName}, tidak dapat digunakan pada {$currentName}.",
            ], 200);
        }

        // Cek minimum pembelian
        if ($amount < $voucher->min_purchase) {
            return response()->json([
                'success' => false,
                'message' => "Syarat minimum belanja voucher ini adalah Rp " . number_format($voucher->min_purchase, 0, ',', '.') . " (total saat ini: Rp " . number_format($amount, 0, ',', '.') . ").",
            ], 200);
        }

        $discountAmount = $voucher->calculateDiscount($amount);
        $finalPrice     = max(0, $amount - $discountAmount);

        return response()->json([
            'success' => true,
            'message' => "Voucher '{$voucher->code}' berhasil diterapkan!",
            'data'    => [
                'code'            => $voucher->code,
                'name'            => $voucher->name,
                'discount_type'   => $voucher->discount_type,
                'discount_value'  => $voucher->discount_value,
                'discount_amount' => $discountAmount,
                'final_price'     => $finalPrice,
            ],
        ], 200);
    }
}
