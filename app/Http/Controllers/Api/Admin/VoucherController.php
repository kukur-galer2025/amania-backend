<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    /**
     * Tampilkan semua voucher, diurutkan terbaru.
     */
    public function index()
    {
        $vouchers = Voucher::orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data'    => $vouchers,
        ]);
    }

    /**
     * Buat voucher baru.
     */
    public function store(Request $request)
    {
        $request->validate([
            'code'           => 'required|string|max:50|unique:vouchers,code',
            'name'           => 'required|string|max:255',
            'discount_type'  => 'required|in:percent,fixed',
            'discount_value' => 'required|integer|min:1',
            'min_purchase'   => 'nullable|integer|min:0',
            'max_uses'       => 'nullable|integer|min:1',
            'applicable_to'  => 'nullable|in:all,e_product,course,event',
            'expires_at'     => 'nullable|date',
            'is_active'      => 'nullable|boolean',
        ]);

        // Validasi tambahan: persen tidak boleh lebih dari 100
        if ($request->discount_type === 'percent' && $request->discount_value > 100) {
            return response()->json([
                'success' => false,
                'message' => 'Nilai diskon persen tidak boleh lebih dari 100.',
            ], 422);
        }

        $expiresAt = null;
        if ($request->filled('expires_at')) {
            $parsed = \Carbon\Carbon::parse($request->expires_at);
            if ($parsed->format('H:i:s') === '00:00:00') {
                $parsed = $parsed->endOfDay();
            }
            $expiresAt = $parsed;
        }

        $voucher = Voucher::create([
            'code'           => strtoupper($request->code),
            'name'           => $request->name,
            'discount_type'  => $request->discount_type,
            'discount_value' => $request->discount_value,
            'min_purchase'   => $request->min_purchase ?? 0,
            'max_uses'       => $request->max_uses,
            'applicable_to'  => $request->applicable_to ?? 'all',
            'expires_at'     => $expiresAt,
            'is_active'      => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Voucher berhasil dibuat.',
            'data'    => $voucher,
        ], 201);
    }

    /**
     * Update voucher.
     */
    public function update(Request $request, $id)
    {
        $voucher = Voucher::findOrFail($id);

        $request->validate([
            'code'           => 'sometimes|required|string|max:50|unique:vouchers,code,' . $id,
            'name'           => 'sometimes|required|string|max:255',
            'discount_type'  => 'sometimes|required|in:percent,fixed',
            'discount_value' => 'sometimes|required|integer|min:1',
            'min_purchase'   => 'nullable|integer|min:0',
            'max_uses'       => 'nullable|integer|min:1',
            'applicable_to'  => 'nullable|in:all,e_product,course,event',
            'expires_at'     => 'nullable|date',
            'is_active'      => 'nullable|boolean',
        ]);

        // Validasi tambahan: persen tidak boleh lebih dari 100
        $discountType  = $request->discount_type ?? $voucher->discount_type;
        $discountValue = $request->discount_value ?? $voucher->discount_value;
        if ($discountType === 'percent' && $discountValue > 100) {
            return response()->json([
                'success' => false,
                'message' => 'Nilai diskon persen tidak boleh lebih dari 100.',
            ], 422);
        }

        $data = $request->only([
            'name', 'discount_type', 'discount_value',
            'min_purchase', 'max_uses', 'applicable_to',
            'is_active',
        ]);

        if ($request->filled('expires_at')) {
            $parsed = \Carbon\Carbon::parse($request->expires_at);
            if ($parsed->format('H:i:s') === '00:00:00') {
                $parsed = $parsed->endOfDay();
            }
            $data['expires_at'] = $parsed;
        }

        if ($request->has('code')) {
            $data['code'] = strtoupper($request->code);
        }

        $voucher->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Voucher berhasil diperbarui.',
            'data'    => $voucher,
        ]);
    }

    /**
     * Hapus voucher.
     */
    public function destroy($id)
    {
        $voucher = Voucher::findOrFail($id);
        $voucher->delete();

        return response()->json([
            'success' => true,
            'message' => 'Voucher berhasil dihapus.',
        ]);
    }

    /**
     * Toggle status aktif voucher.
     */
    public function toggleActive($id)
    {
        $voucher = Voucher::findOrFail($id);
        $voucher->update(['is_active' => !$voucher->is_active]);

        return response()->json([
            'success'   => true,
            'message'   => 'Status voucher berhasil diubah.',
            'is_active' => $voucher->is_active,
        ]);
    }
}
