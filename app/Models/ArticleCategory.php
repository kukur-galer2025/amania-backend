<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ArticleCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    // Relasi: Satu kategori punya banyak artikel
    public function articles()
    {
        return $this->hasMany(Article::class, 'article_category_id');
    }

    // Otomatis buat slug saat name diisi (Opsional tapi membantu)
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($category) {
            $category->slug = Str::slug($category->name);
        });
    }
}