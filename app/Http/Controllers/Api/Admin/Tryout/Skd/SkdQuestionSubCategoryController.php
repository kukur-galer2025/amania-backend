<?php

namespace App\Http\Controllers\Api\Admin\Tryout\Skd;

use App\Http\Controllers\Controller;
use App\Models\SkdQuestionSubCategory;
use Illuminate\Http\Request;

class SkdQuestionSubCategoryController extends Controller
{
    // Mengambil semua sub-kategori (Bisa difilter via URL ?main_category=twk)
    public function index(Request $request)
    {
        $query = SkdQuestionSubCategory::query();

        if ($request->has('main_category')) {
            $query->where('main_category', $request->main_category);
        }

        $subCategories = $query->orderBy('main_category')->latest()->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar Sub-Kategori SKD',
            'data' => $subCategories
        ]);
    }

    // Menyimpan sub-kategori baru
    public function store(Request $request)
    {
        $request->validate([
            'main_category' => 'required|in:twk,tiu,tkp',
            'name' => 'required|string|max:255',
        ]);

        $subCategory = SkdQuestionSubCategory::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Sub-Kategori berhasil ditambahkan',
            'data' => $subCategory
        ], 201);
    }

    // Mengambil detail 1 sub-kategori
    public function show($id)
    {
        $subCategory = SkdQuestionSubCategory::findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail Sub-Kategori',
            'data' => $subCategory
        ]);
    }

    // Mengubah data sub-kategori
    public function update(Request $request, $id)
    {
        $subCategory = SkdQuestionSubCategory::findOrFail($id);

        $request->validate([
            'main_category' => 'required|in:twk,tiu,tkp',
            'name' => 'required|string|max:255',
        ]);

        $subCategory->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Sub-Kategori berhasil diubah',
            'data' => $subCategory
        ]);
    }

    // Menghapus sub-kategori
    public function destroy($id)
    {
        $subCategory = SkdQuestionSubCategory::findOrFail($id);
        
        // Pengecekan: Jangan hapus jika sub-kategori ini sudah dipakai di soal
        if ($subCategory->questions()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal dihapus! Sub-Kategori ini sudah memiliki soal yang terhubung.'
            ], 400);
        }

        $subCategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sub-Kategori berhasil dihapus'
        ]);
    }
}