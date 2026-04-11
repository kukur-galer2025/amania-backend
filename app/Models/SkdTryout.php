<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SkdTryout extends Model
{
    use HasFactory;

    protected $table = 'skd_tryouts';

    protected $fillable = [
        'skd_tryout_category_id', // 🔥 Ini pengganti 'category' yang lama
        'title',
        'slug',
        'description',
        'duration_minutes',
        'price',
        'discount_price',
        'discount_start_date',
        'discount_end_date',
        'is_hots',
        'is_active',
    ];

    protected $casts = [
        'is_hots' => 'boolean',
        'is_active' => 'boolean',
        'discount_start_date' => 'datetime',
        'discount_end_date' => 'datetime',
    ];

    // 🔥 RELASI KE KATEGORI TRYOUT (CPNS, Kedinasan, BUMN, dll) 🔥
    public function category()
    {
        return $this->belongsTo(SkdTryoutCategory::class, 'skd_tryout_category_id');
    }

    // Relasi ke Soal
    public function questions()
    {
        return $this->hasMany(SkdQuestion::class);
    }
}