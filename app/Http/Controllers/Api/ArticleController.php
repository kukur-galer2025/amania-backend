<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    /**
     * Tampilkan semua artikel untuk halaman utama/grid
     * Hanya menampilkan artikel yang sudah di-ACC (is_published = 1)
     */
    public function index()
    {
        // Menarik data artikel beserta kategori dan penulis (Organizer/Admin)
        $articles = Article::with(['category', 'author'])
            ->where('is_published', 1)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $articles
        ]);
    }

    /**
     * Tampilkan detail artikel (termasuk tags) untuk halaman baca
     */
    public function show($slug)
    {
        $article = Article::with(['category', 'author'])
            ->where('slug', $slug)
            ->where('is_published', 1)
            ->first();

        if (!$article) {
            return response()->json([
                'success' => false,
                'message' => 'Artikel tidak ditemukan atau belum dipublikasikan'
            ], 404);
        }

        // Karena sudah ada $casts di model, $article->tags otomatis sudah jadi array
        return response()->json([
            'success' => true,
            'data' => $article
        ]);
    }
}