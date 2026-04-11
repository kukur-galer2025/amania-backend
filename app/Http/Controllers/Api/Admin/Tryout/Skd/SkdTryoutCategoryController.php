<?php

namespace App\Http\Controllers\Api\Admin\Tryout\Skd;

use App\Http\Controllers\Controller;
use App\Models\SkdTryoutCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SkdTryoutCategoryController extends Controller
{
    // Mengambil semua data kategori
    public function index()
    {
        $categories = SkdTryoutCategory::orderBy('name', 'asc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar Kategori Tryout SKD',
            'data' => $categories
        ]);
    }

    // Menyimpan kategori baru
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:skd_tryout_categories,name',
        ]);

        $category = SkdTryoutCategory::create([
            'name' => $request->name,
            // Otomatis bikin slug URL dari nama
            'slug' => Str::slug($request->name),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil ditambahkan',
            'data' => $category
        ], 201);
    }

    // Mengubah data kategori
    public function update(Request $request, $id)
    {
        $category = SkdTryoutCategory::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255|unique:skd_tryout_categories,name,' . $id,
        ]);

        $category->update([
            'name' => $request->name,
            // Slug ikut diupdate kalau namanya berubah
            'slug' => Str::slug($request->name),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil diubah',
            'data' => $category
        ]);
    }

    // Menghapus kategori
    public function destroy($id)
    {
        $category = SkdTryoutCategory::findOrFail($id);
        
        // Catatan: Karena di migrasi kita pakai nullOnDelete(), 
        // maka Tryout yang pakai kategori ini tidak akan ikut terhapus,
        // melainkan kolom skd_tryout_category_id-nya akan menjadi NULL.
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dihapus'
        ]);
    }
}