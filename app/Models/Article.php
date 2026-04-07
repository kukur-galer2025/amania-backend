<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    protected $fillable = [
        'title', 
        'slug', 
        'article_category_id', 
        'image', 
        'content', 
        'read_time', 
        'is_published',
        'user_id', // 🔥 Wajib ada agar Author ID bisa disimpan
        'tags'     // Untuk simpan array tags
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'tags' => 'array', // Mengubah JSON di DB menjadi Array PHP secara otomatis
    ];

    /**
     * Relasi ke Kategori Artikel
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'article_category_id');
    }

    /**
     * Relasi ke Penulis (Superadmin/Organizer)
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}