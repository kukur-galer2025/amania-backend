<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EProductPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_code',
        'user_id',
        'e_product_id',
        'amount',
        'snap_token',
        'payment_url',
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