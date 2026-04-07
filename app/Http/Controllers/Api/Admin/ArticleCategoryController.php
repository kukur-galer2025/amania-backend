<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArticleCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ArticleCategoryController extends Controller
{
    // Ambil semua kategori
    public function index()
    {
        $categories = ArticleCategory::latest()->get();
        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    // Simpan kategori baru
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:article_categories,name',
        ]);

        $category = ArticleCategory::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dibuat!',
            'data' => $category
        ], 210);
    }

    // Update kategori
    public function update(Request $request, $id)
    {
        $category = ArticleCategory::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255|unique:article_categories,name,' . $id,
        ]);

        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil diperbarui!',
            'data' => $category
        ]);
    }

    // Hapus kategori
    public function destroy($id)
    {
        $category = ArticleCategory::findOrFail($id);
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dihapus!'
        ]);
    }
}