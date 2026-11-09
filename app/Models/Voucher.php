<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Voucher extends Model
{
    protected $fillable = [
        'code',
        'name',
        'discount_type',
        'discount_value',
        'max_uses',
        'used_count',
        'min_purchase',
        'is_active',
        'applicable_to',
        'expires_at',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'expires_at' => 'datetime',
    ];

    /**
     * Cek apakah voucher masih valid (aktif, belum expired, belum habis kuota).
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at !== null) {
            // Jika jam 00:00:00 (user hanya pilih tanggal di picker), anggap berlaku sampai akhir hari
            $expiry = $this->expires_at->format('H:i:s') === '00:00:00'
                ? $this->expires_at->copy()->endOfDay()
                : $this->expires_at;

            if ($expiry->isPast()) {
                return false;
            }
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    /**
     * Hitung jumlah diskon berdasarkan tipe dan nilai voucher.
     *
     * @param  int  $amount  Harga asli (dalam rupiah)
     * @return int           Jumlah diskon (dalam rupiah)
     */
    public function calculateDiscount(int $amount): int
    {
        if ($this->discount_type === 'percent') {
            $discount = (int) round($amount * $this->discount_value / 100);
        } else {
            // fixed
            $discount = (int) $this->discount_value;
        }

        // Diskon tidak boleh melebihi harga asli
        return min($discount, $amount);
    }
}
