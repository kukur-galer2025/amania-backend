<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SkdTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'skd_tryout_id',
        'reference',
        'merchant_ref',
        'amount',
        'payment_method',
        'payment_name',
        'checkout_url',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    // Relasi ke tabel users
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke tabel skd_tryouts
    public function tryout()
    {
        return $this->belongsTo(SkdTryout::class, 'skd_tryout_id');
    }
}