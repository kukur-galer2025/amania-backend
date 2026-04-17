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
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240', // Maks 10MB
            // Wajib pilih salah satu: file upload ATAU link gdrive
            'file_upload' => 'required_without:file_link|nullable|file|mimes:pdf,zip,rar|max:51200', // Maks 50MB
            'file_link'   => 'required_without:file_upload|nullable|string|url', 
            'is_published' => 'required|boolean'
        ]);

        $data = $request->only(['title', 'description', 'price', 'is_published']);
        $data['slug'] = Str::slug($request->title) . '-' . uniqid();
        $data['user_id'] = $request->user()->id; 

        // Upload Cover
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('e_products/covers', 'public');
        }

        // Eksekusi Tipe File Asli
        if ($request->hasFile('file_upload')) {
            $data['file_path'] = $request->file('file_upload')->store('e_products/files', 'public');
        } elseif ($request->filled('file_link')) {
            $data['file_path'] = $request->file_link;
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
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240', // Maks 10MB
            'file_upload' => 'nullable|file|mimes:pdf,zip,rar|max:51200', // Maks 50MB
            'file_link'   => 'nullable|string|url',
            'is_published' => 'required|boolean'
        ]);

        $data = $request->only(['title', 'description', 'price', 'is_published']);
        
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

        // Update File Asli
        if ($request->hasFile('file_upload')) {
            // Hapus file fisik lama
            if ($product->file_path && !Str::startsWith($product->file_path, ['http://', 'https://']) && Storage::disk('public')->exists($product->file_path)) {
                Storage::disk('public')->delete($product->file_path);
            }
            $data['file_path'] = $request->file('file_upload')->store('e_products/files', 'public');
        } elseif ($request->filled('file_link')) {
            // Jika beralih ke Link, hapus file fisik lama (jika ada)
            if ($product->file_path && !Str::startsWith($product->file_path, ['http://', 'https://']) && Storage::disk('public')->exists($product->file_path)) {
                Storage::disk('public')->delete($product->file_path);
            }
            $data['file_path'] = $request->file_link;
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

        if ($product->cover_image && !Str::startsWith($product->cover_image, ['http://', 'https://']) && Storage::disk('public')->exists($product->cover_image)) {
            Storage::disk('public')->delete($product->cover_image);
        }
        if ($product->file_path && !Str::startsWith($product->file_path, ['http://', 'https://']) && Storage::disk('public')->exists($product->file_path)) {
            Storage::disk('public')->delete($product->file_path);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'E-Produk berhasil dihapus!'
        ]);
    }
}