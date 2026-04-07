<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Article;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    /**
     * Mencari Event dan Artikel berdasarkan kata kunci (q)
     * Disesuaikan dengan arsitektur Query Parameter (?slug=)
     */
    public function search(Request $request)
    {
        $query = $request->get('q');
        
        // 1. Jika query kosong
        if (empty($query)) {
            return response()->json([
                'success' => true,
                'events' => [],
                'articles' => []
            ], 200);
        }

        // 2. Cari di tabel EVENTS (Katalog Program)
        $events = Event::select('id', 'title', 'slug', 'image', 'start_time', 'venue')
                        ->where('title', 'like', "%{$query}%")
                        ->limit(5)
                        ->get()
                        ->map(function ($event) {
                            $event->type = 'event'; 
                            // 🔥 DISESUAIKAN: Menuju folder /events/detail dengan query param
                            $event->link = "/events/detail?slug={$event->slug}"; 
                            return $event;
                        });

        // 3. Cari di tabel ARTICLES (Berita/Jurnal)
        // Kita eager load 'author' agar data pengirim muncul di dropdown search
        $articles = Article::with('author:id,name') 
                            ->select('id', 'title', 'slug', 'image', 'created_at', 'user_id')
                            ->where('title', 'like', "%{$query}%")
                            ->limit(5)
                            ->get()
                            ->map(function ($article) {
                                $article->type = 'article';
                                // 🔥 DISESUAIKAN: Menuju folder /articles/read dengan query param
                                $article->link = "/articles/read?slug={$article->slug}";
                                
                                // Ambil nama author saja untuk meringankan beban data
                                $article->author_name = $article->author->name ?? 'Amania Team';
                                unset($article->author); // hapus object relation agar JSON bersih
                                return $article;
                            });

        // 4. Response JSON
        return response()->json([
            'success' => true,
            'message' => 'Pencarian global berhasil',
            'events' => $events,
            'articles' => $articles
        ], 200);
    }
}