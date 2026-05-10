<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'e_product_category_id', // 🔥 Kolom relasi kategori
        'title',
        'slug',
        'description',
        'price',
        'cover_image',
        // 'file_path', <--- SUDAH DIHAPUS (Diganti dengan relasi multi-materials)
        'is_published',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // 🔥 RELASI KE KATEGORI E-PRODUK 🔥
    public function category(): BelongsTo
    {
        return $this->belongsTo(EProductCategory::class, 'e_product_category_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(EProductPurchase::class);
    }

    // 🔥 WAJIB DITAMBAHKAN AGAR FITUR RATING BINTANG BERJALAN 🔥
    public function reviews(): HasMany
    {
        return $this->hasMany(EProductReview::class);
    }

    // 🔥 RELASI BARU UNTUK MULTI MATERI E-PRODUK 🔥
    public function materials(): HasMany
    {
        return $this->hasMany(EProductMaterial::class, 'e_product_id');
    }
}