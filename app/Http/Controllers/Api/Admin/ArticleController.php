<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Helpers\ImageHelper;
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
        if ($currentUser->role === 'creator') {
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
        if ($currentUser->role === 'creator' && $article->user_id !== $currentUser->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
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
            $imagePath = ImageHelper::compressAndStore($request->file('image'), 'articles', 1200, 900, 80);
        }

        $tags = $request->tags;
        if (is_string($tags) && trim($tags) !== '') {
            $tags = array_filter(array_map('trim', explode(',', $tags)));
        } else {
            $tags = [];
        }

        $isPublished = (bool)$request->is_published;

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

        $message = 'Artikel berhasil diterbitkan!';

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
        if ($currentUser->role === 'creator' && $article->user_id !== $currentUser->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
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
        $data['is_published'] = (bool)$request->is_published;

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
            $data['image'] = ImageHelper::compressAndStore($request->file('image'), 'articles', 1200, 900, 80);
        }

        $article->update($data);

        $message = 'Artikel berhasil diperbarui!';

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
        if ($currentUser->role === 'creator' && $article->user_id !== $currentUser->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
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