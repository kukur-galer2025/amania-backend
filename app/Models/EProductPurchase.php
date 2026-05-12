<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EProductPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'tripay_reference',
        'user_id',
        // 'e_product_id', -> 🔥 SUDAH DIHAPUS, AMAN UNTUK APRIORI
        'amount',
        'checkout_url',
        'payment_method', 
        'expired_time', 
        'status'
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // 🔥 RELASI BARU: 1 Invoice bisa berisi BANYAK Produk (Items) 🔥
    public function items(): HasMany
    {
        return $this->hasMany(EProductOrderItem::class, 'e_product_purchase_id');
    }
}