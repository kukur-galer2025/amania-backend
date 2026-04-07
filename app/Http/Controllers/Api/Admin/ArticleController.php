<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ArticleController extends Controller
{
    /**
     * TAMPILKAN SEMUA ARTIKEL
     */
    public function index(Request $request)
    {
        $currentUser = $request->user();
        
        $query = Article::with(['category', 'author']);

        // 🔥 LOGIKA MULTI-TENANT 🔥
        // Jika yang login adalah organizer, hanya tampilkan artikel miliknya
        if ($currentUser->role === 'organizer') {
            $query->where('user_id', $currentUser->id);
        }

        $articles = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $articles
        ]);
    }

    /**
     * TAMPILKAN SATU ARTIKEL UNTUK DIEDIT
     */
    public function show(Request $request, $id)
    {
        $currentUser = $request->user();
        $article = Article::with('category')->findOrFail($id);

        // 🔥 PROTEKSI MULTI-TENANT 🔥
        if ($currentUser->role === 'organizer' && $article->user_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda hanya bisa melihat artikel yang Anda tulis.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $article
        ]);
    }

    /**
     * TAMBAH ARTIKEL BARU
     */
    public function store(Request $request)
    {
        $currentUser = $request->user();

        $request->validate([
            'title' => 'required|string|max:255',
            'article_category_id' => 'required|exists:article_categories,id',
            'content' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120', // Maks 5MB
            'read_time' => 'required|integer|min:1',
            'is_published' => 'required|in:0,1,true,false',
            'tags' => 'nullable|string'
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('articles', 'public');
        }

        $tags = $request->tags;
        if (is_string($tags) && trim($tags) !== '') {
            $tags = array_filter(array_map('trim', explode(',', $tags)));
        } else {
            $tags = [];
        }

        // 🔥 PENENTUAN STATUS PUBLISH (SISTEM MODERASI) 🔥
        // Organizer yang membuat artikel otomatis masuk ke Draft (is_published = 0)
        // agar harus di-review Superadmin dulu sebelum tayang ke publik.
        $isPublished = (bool)$request->is_published;
        if ($currentUser->role === 'organizer') {
             $isPublished = false; 
        }

        $article = Article::create([
            'title' => $request->title,
            'slug' => Str::slug($request->title) . '-' . uniqid(),
            'article_category_id' => $request->article_category_id,
            'content' => $request->content,
            'image' => $imagePath,
            'read_time' => $request->read_time ?? 5,
            'is_published' => $isPublished,
            'user_id' => $currentUser->id, // Simpan ID penulis (Superadmin / Organizer)
            'tags' => $tags, 
        ]);

        $message = $currentUser->role === 'organizer' 
                 ? 'Artikel berhasil disimpan. Menunggu persetujuan Admin sebelum ditayangkan!' 
                 : 'Artikel berhasil diterbitkan!';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $article
        ]);
    }

    /**
     * UPDATE ARTIKEL
     */
    public function update(Request $request, $id)
    {
        $currentUser = $request->user();
        $article = Article::findOrFail($id);

        // 🔥 PROTEKSI MULTI-TENANT 🔥
        if ($currentUser->role === 'organizer' && $article->user_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda hanya bisa mengedit artikel yang Anda tulis.'
            ], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'article_category_id' => 'required|exists:article_categories,id',
            'content' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'read_time' => 'required|integer|min:1', 
            'is_published' => 'required|in:0,1,true,false',
            'tags' => 'nullable|string'
        ]);

        $data = $request->only(['title', 'article_category_id', 'content', 'read_time']);
        
        // Atur Status Publish
        if ($currentUser->role === 'superadmin') {
            $data['is_published'] = (bool)$request->is_published;
        } else {
            // Jika organizer mengedit artikelnya, status kembalikan ke draft
            // untuk direview ulang oleh Superadmin.
            $data['is_published'] = false; 
        }

        if ($request->title !== $article->title) {
            $data['slug'] = Str::slug($request->title) . '-' . uniqid();
        }

        // Update Tags
        if ($request->has('tags')) {
            $tags = $request->tags;
            if (is_string($tags) && trim($tags) !== '') {
                $data['tags'] = array_filter(array_map('trim', explode(',', $tags)));
            } else {
                $data['tags'] = [];
            }
        }

        if ($request->hasFile('image')) {
            if ($article->image && Storage::disk('public')->exists($article->image)) {
                Storage::disk('public')->delete($article->image);
            }
            $data['image'] = $request->file('image')->store('articles', 'public');
        }

        $article->update($data);

        $message = $currentUser->role === 'organizer' 
                 ? 'Artikel diperbarui. Status kembali ke Menunggu Review.' 
                 : 'Artikel berhasil diperbarui!';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $article
        ]);
    }

    /**
     * HAPUS ARTIKEL
     */
    public function destroy(Request $request, $id)
    {
        $currentUser = $request->user();
        $article = Article::findOrFail($id);

        // 🔥 PROTEKSI MULTI-TENANT 🔥
        if ($currentUser->role === 'organizer' && $article->user_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda hanya bisa menghapus artikel Anda sendiri.'
            ], 403);
        }

        if ($article->image && Storage::disk('public')->exists($article->image)) {
            Storage::disk('public')->delete($article->image);
        }
        
        $article->delete();

        return response()->json([
            'success' => true,
            'message' => 'Artikel berhasil dihapus!'
        ]);
    }
}