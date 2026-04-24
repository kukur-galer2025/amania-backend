<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EProductReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'e_product_id',
        'user_id',
        'rating',
        'review',
    ];

    // Relasi ke User (Pembeli)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke E-Produk
    public function eProduct()
    {
        return $this->belongsTo(EProduct::class, 'e_product_id');
    }
}