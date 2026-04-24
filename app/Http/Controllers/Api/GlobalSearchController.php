<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Article;
use App\Models\EProduct; // 🔥 Wajib Import Model EProduct
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    /**
     * Mencari Event, Artikel, dan E-Produk berdasarkan kata kunci (q)
     */
    public function search(Request $request)
    {
        $query = $request->get('q');
        
        // 1. Jika query kosong
        if (empty($query)) {
            return response()->json([
                'success' => true,
                'events' => [],
                'articles' => [],
                'eproducts' => [] // Tambahan array kosong
            ], 200);
        }

        // 2. Cari di tabel EVENTS (Katalog Program/Webinar)
        $events = Event::select('id', 'title', 'slug', 'image', 'start_time', 'venue')
                        ->where('title', 'like', "%{$query}%")
                        ->limit(5)
                        ->get()
                        ->map(function ($event) {
                            $event->type = 'event'; 
                            // 🔥 PERBAIKAN: Menggunakan Dynamic Routing Next.js
                            $event->link = "/events/{$event->slug}"; 
                            return $event;
                        });

        // 3. Cari di tabel ARTICLES (Berita/Jurnal)
        $articles = Article::with('author:id,name') 
                            ->select('id', 'title', 'slug', 'image', 'created_at', 'user_id')
                            ->where('title', 'like', "%{$query}%")
                            ->limit(5)
                            ->get()
                            ->map(function ($article) {
                                $article->type = 'article';
                                // 🔥 PERBAIKAN: Menggunakan Dynamic Routing Next.js
                                $article->link = "/articles/{$article->slug}";
                                
                                $article->author_name = $article->author->name ?? 'Amania Team';
                                unset($article->author); 
                                return $article;
                            });

        // 4. 🔥 TAMBAHAN: Cari di tabel E-PRODUCTS (Katalog Digital) 🔥
        $eProducts = EProduct::with('author:id,name')
                             ->select('id', 'title', 'slug', 'cover_image as image', 'price', 'user_id')
                             ->where('is_published', true) // Pastikan hanya yg di-publish yg muncul
                             ->where('title', 'like', "%{$query}%")
                             ->limit(5)
                             ->get()
                             ->map(function ($product) {
                                 $product->type = 'eproduct';
                                 $product->link = "/e-products/{$product->slug}";
                                 $product->author_name = $product->author->name ?? 'Amania Official';
                                 
                                 // Format harga untuk ditampilkan di dropdown search
                                 $product->formatted_price = $product->price == 0 ? 'Gratis' : 'Rp ' . number_format($product->price, 0, ',', '.');
                                 
                                 unset($product->author);
                                 return $product;
                             });

        // 5. Response JSON Lengkap
        return response()->json([
            'success' => true,
            'message' => 'Pencarian global berhasil',
            'events' => $events,
            'articles' => $articles,
            'eproducts' => $eProducts // Kembalikan data e-produk
        ], 200);
    }
}