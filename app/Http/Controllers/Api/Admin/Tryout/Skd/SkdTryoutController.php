<?php

namespace App\Http\Controllers\Api\Admin\Tryout\Skd;

use App\Http\Controllers\Controller;
use App\Models\SkdTryout;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SkdTryoutController extends Controller
{
    // Mengambil semua data tryout (list di halaman Admin)
    public function index()
    {
        // 🔥 Tambahkan with('category') agar relasinya terbawa ke Frontend
        $tryouts = SkdTryout::with(['category'])->withCount('questions')->latest()->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Daftar Tryout SKD',
            'data' => $tryouts
        ]);
    }

    // Menyimpan tryout baru
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            // 🔥 Validasi dirubah dari enum menjadi id (foreign key) 🔥
            'skd_tryout_category_id' => 'required|exists:skd_tryout_categories,id', 
            'duration_minutes' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            
            // Validasi Diskon & HOTS
            'discount_price' => 'nullable|numeric|min:0',
            'discount_start_date' => 'nullable|date',
            'discount_end_date' => 'nullable|date|after_or_equal:discount_start_date',
            'is_hots' => 'nullable|boolean',
        ]);

        $tryout = SkdTryout::create([
            'title' => $request->title,
            'slug' => Str::slug($request->title . '-' . time()),
            'description' => $request->description,
            // 🔥 Parameter dirubah menjadi id 🔥
            'skd_tryout_category_id' => $request->skd_tryout_category_id,
            'duration_minutes' => $request->duration_minutes,
            'price' => $request->price,
            
            'discount_price' => $request->discount_price,
            'discount_start_date' => $request->discount_start_date,
            'discount_end_date' => $request->discount_end_date,
            'is_hots' => $request->is_hots ?? false,
            
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tryout SKD berhasil ditambahkan',
            'data' => $tryout
        ], 201);
    }

    // Mengambil 1 detail tryout beserta daftar soalnya
    public function show($id)
    {
        // 🔥 Tambahkan with('category') juga di sini
        $tryout = SkdTryout::with(['category', 'questions.subCategory'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail Tryout SKD',
            'data' => $tryout
        ]);
    }

    // Mengubah data tryout
    public function update(Request $request, $id)
    {
        $tryout = SkdTryout::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            // 🔥 Validasi dirubah dari enum menjadi id (foreign key) 🔥
            'skd_tryout_category_id' => 'required|exists:skd_tryout_categories,id',
            'duration_minutes' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            
            'discount_price' => 'nullable|numeric|min:0',
            'discount_start_date' => 'nullable|date',
            'discount_end_date' => 'nullable|date|after_or_equal:discount_start_date',
            'is_hots' => 'nullable|boolean',
        ]);

        $tryout->update([
            'title' => $request->title,
            'description' => $request->description,
            // 🔥 Parameter dirubah menjadi id 🔥
            'skd_tryout_category_id' => $request->skd_tryout_category_id,
            'duration_minutes' => $request->duration_minutes,
            'price' => $request->price,
            
            'discount_price' => $request->discount_price,
            'discount_start_date' => $request->discount_start_date,
            'discount_end_date' => $request->discount_end_date,
            'is_hots' => $request->has('is_hots') ? $request->is_hots : $tryout->is_hots,
            
            'is_active' => $request->has('is_active') ? $request->is_active : $tryout->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tryout SKD berhasil diubah',
            'data' => $tryout
        ]);
    }

    // Menghapus tryout (otomatis menghapus semua soal di dalamnya berkat cascadeOnDelete)
    public function destroy($id)
    {
        $tryout = SkdTryout::findOrFail($id);
        $tryout->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tryout SKD berhasil dihapus'
        ]);
    }
}