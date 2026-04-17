<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EProductPurchase extends Model
{
    use HasFactory;

    // 🔥 SESUAIKAN DENGAN KOLOM MIGRASI TRIPAY 🔥
    protected $fillable = [
        'reference',
        'tripay_reference',
        'user_id',
        'e_product_id',
        'amount',
        'checkout_url',
        'status'
    ];

    // Pembeli
    public function buyer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Produk yang dibeli
    public function product()
    {
        return $this->belongsTo(EProduct::class, 'e_product_id');
    }
}