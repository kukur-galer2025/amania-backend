<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class EProductController extends Controller
{
    /**
     * TAMPILKAN SEMUA E-PRODUK
     */
    public function index()
    {
        // 🔥 PERBAIKAN: Load relasi 'category' agar nama kategori muncul di tabel Admin
        $products = EProduct::with(['author', 'category'])->latest()->get();
        return response()->json(['success' => true, 'data' => $products]);
    }

    /**
     * TAMPILKAN SATU E-PRODUK (Untuk Edit)
     */
    public function show($id)
    {
        // 🔥 PERBAIKAN: Tambahkan relasi 'materials' agar bisa ditampilkan di Frontend
        $product = EProduct::with(['category', 'materials'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $product]);
    }

    /**
     * TAMBAH E-PRODUK BARU
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'e_product_category_id' => 'required|exists:e_product_categories,id', // 🔥 WAJIB ADA KATEGORI
            'description' => 'required|string',
            'price' => 'required|integer|min:0',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240', // Maks 10MB
            // 🔥 VALIDASI FILE_UPLOAD & FILE_LINK SUDAH DIHAPUS DARI SINI 🔥
            'is_published' => 'required|boolean'
        ]);

        $data = $request->only(['title', 'e_product_category_id', 'description', 'price', 'is_published']);
        $data['slug'] = Str::slug($request->title) . '-' . uniqid();
        $data['user_id'] = $request->user()->id; 

        // Upload Cover
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('e_products/covers', 'public');
        }

        // 🔥 LOGIKA UNTUK MENYIMPAN FILE_PATH SUDAH DIHAPUS 🔥

        $product = EProduct::create($data);

        return response()->json([
            'success' => true,
            'message' => 'E-Produk berhasil ditambahkan!',
            'data' => $product
        ], 201);
    }

    /**
     * UPDATE E-PRODUK
     */
    public function update(Request $request, $id)
    {
        $product = EProduct::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'e_product_category_id' => 'required|exists:e_product_categories,id', // 🔥 WAJIB ADA KATEGORI
            'description' => 'required|string',
            'price' => 'required|integer|min:0',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240', // Maks 10MB
            // 🔥 VALIDASI FILE_UPLOAD & FILE_LINK SUDAH DIHAPUS DARI SINI 🔥
            'is_published' => 'required|boolean'
        ]);

        $data = $request->only(['title', 'e_product_category_id', 'description', 'price', 'is_published']);
        
        if ($request->title !== $product->title) {
            $data['slug'] = Str::slug($request->title) . '-' . uniqid();
        }

        // Update Cover
        if ($request->hasFile('cover_image')) {
            // Hapus cover lama jika bukan link eksternal
            if ($product->cover_image && !Str::startsWith($product->cover_image, ['http://', 'https://']) && Storage::disk('public')->exists($product->cover_image)) {
                Storage::disk('public')->delete($product->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('e_products/covers', 'public');
        }

        // 🔥 LOGIKA UNTUK MENGUPDATE FILE_PATH SUDAH DIHAPUS 🔥

        $product->update($data);

        return response()->json([
            'success' => true,
            'message' => 'E-Produk berhasil diperbarui!',
            'data' => $product
        ]);
    }

    /**
     * HAPUS E-PRODUK
     */
    public function destroy($id)
    {
        $product = EProduct::findOrFail($id);

        // Hapus file cover fisik
        if ($product->cover_image && !Str::startsWith($product->cover_image, ['http://', 'https://']) && Storage::disk('public')->exists($product->cover_image)) {
            Storage::disk('public')->delete($product->cover_image);
        }
        
        // 🔥 HAPUS FILE FISIK DARI MATERI (ZIP/PDF) SEBELUM MENGHAPUS PRODUK 🔥
        foreach ($product->materials as $mat) {
            if ($mat->type === 'file' && $mat->file_path && Storage::disk('public')->exists($mat->file_path)) {
                Storage::disk('public')->delete($mat->file_path);
            }
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'E-Produk dan materinya berhasil dihapus!'
        ]);
    }
}