<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'e_product_category_id', // 🔥 Kolom relasi kategori baru ditambahkan ke sini
        'title',
        'slug',
        'description',
        'price',
        'cover_image',
        'file_path',
        'is_published',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // 🔥 RELASI KE KATEGORI E-PRODUK 🔥
    public function category()
    {
        return $this->belongsTo(EProductCategory::class, 'e_product_category_id');
    }

    public function purchases()
    {
        return $this->hasMany(EProductPurchase::class);
    }

    // 🔥 WAJIB DITAMBAHKAN AGAR FITUR RATING BINTANG BERJALAN 🔥
    public function reviews()
    {
        return $this->hasMany(EProductReview::class);
    }
}