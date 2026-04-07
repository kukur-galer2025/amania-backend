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
        $products = EProduct::with('author')->latest()->get();
        return response()->json(['success' => true, 'data' => $products]);
    }

    /**
     * TAMPILKAN SATU E-PRODUK (Untuk Edit)
     */
    public function show($id)
    {
        $product = EProduct::findOrFail($id);
        return response()->json(['success' => true, 'data' => $product]);
    }

    /**
     * TAMBAH E-PRODUK BARU
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|integer|min:0',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240', // Cover maks 2MB
            'file_path' => 'required|file|mimes:pdf,zip,rar|max:512000', // File asli maks 50MB
            'is_published' => 'required|boolean'
        ]);

        $data = $request->only(['title', 'description', 'price', 'is_published']);
        $data['slug'] = Str::slug($request->title) . '-' . uniqid();
        $data['user_id'] = $request->user()->id; // Superadmin yang upload

        // Upload Cover
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('e_products/covers', 'public');
        }

        // Upload File Asli (E-Book / Template) - Ditaruh di private/public folder tergantung keamanan
        // Untuk saat ini kita taruh di public agar mudah diakses dengan link token nantinya
        if ($request->hasFile('file_path')) {
            $data['file_path'] = $request->file('file_path')->store('e_products/files', 'public');
        }

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
            'description' => 'required|string',
            'price' => 'required|integer|min:0',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'file_path' => 'nullable|file|mimes:pdf,zip,rar|max:51200', // Boleh kosong kalau gak ganti file
            'is_published' => 'required|boolean'
        ]);

        $data = $request->only(['title', 'description', 'price', 'is_published']);
        
        if ($request->title !== $product->title) {
            $data['slug'] = Str::slug($request->title) . '-' . uniqid();
        }

        // Update Cover
        if ($request->hasFile('cover_image')) {
            if ($product->cover_image && Storage::disk('public')->exists($product->cover_image)) {
                Storage::disk('public')->delete($product->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('e_products/covers', 'public');
        }

        // Update File Asli
        if ($request->hasFile('file_path')) {
            if ($product->file_path && Storage::disk('public')->exists($product->file_path)) {
                Storage::disk('public')->delete($product->file_path);
            }
            $data['file_path'] = $request->file('file_path')->store('e_products/files', 'public');
        }

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

        // Hapus file fisik
        if ($product->cover_image && Storage::disk('public')->exists($product->cover_image)) {
            Storage::disk('public')->delete($product->cover_image);
        }
        if ($product->file_path && Storage::disk('public')->exists($product->file_path)) {
            Storage::disk('public')->delete($product->file_path);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'E-Produk berhasil dihapus!'
        ]);
    }
}