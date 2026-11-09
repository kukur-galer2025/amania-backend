<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    /**
     * Validasi kode voucher dan hitung diskon untuk member.
     *
     * Request:
     *   - code   : string (required)
     *   - amount : integer (required) — harga asli sebelum diskon
     *   - type   : string (required) — 'e_product' atau 'course'
     *
     * Response:
     *   {
     *     success: true,
     *     data: {
     *       code, name, discount_type, discount_value,
     *       discount_amount, final_price
     *     }
     *   }
     */
    public function validate(Request $request)
    {
        $request->validate([
            'code'   => 'required|string',
            'amount' => 'required|integer|min:0',
            'type'   => 'required|in:e_product,course',
        ]);

        $code   = strtoupper(trim($request->code));
        $amount = (int) $request->amount;
        $type   = $request->type;

        $voucher = Voucher::where('code', $code)->first();

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Kode voucher tidak ditemukan.',
            ], 404);
        }

        if (!$voucher->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher sudah tidak berlaku (expired, habis kuota, atau dinonaktifkan).',
            ], 422);
        }

        // Cek applicable_to
        if ($voucher->applicable_to !== 'all' && $voucher->applicable_to !== $type) {
            $typeName = $type === 'e_product' ? 'E-Product' : 'Kursus';
            return response()->json([
                'success' => false,
                'message' => "Voucher ini hanya berlaku untuk " . ($voucher->applicable_to === 'e_product' ? 'E-Product' : 'Kursus') . ", bukan {$typeName}.",
            ], 422);
        }

        // Cek minimum pembelian
        if ($amount < $voucher->min_purchase) {
            return response()->json([
                'success' => false,
                'message' => 'Total pembelian tidak memenuhi minimum penggunaan voucher (Rp ' . number_format($voucher->min_purchase, 0, ',', '.') . ').',
            ], 422);
        }

        $discountAmount = $voucher->calculateDiscount($amount);
        $finalPrice     = max(0, $amount - $discountAmount);

        return response()->json([
            'success' => true,
            'message' => 'Voucher valid!',
            'data'    => [
                'code'            => $voucher->code,
                'name'            => $voucher->name,
                'discount_type'   => $voucher->discount_type,
                'discount_value'  => $voucher->discount_value,
                'discount_amount' => $discountAmount,
                'final_price'     => $finalPrice,
            ],
        ]);
    }
}
