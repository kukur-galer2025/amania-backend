<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EProductMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EProductMaterialController extends Controller
{
    /**
     * TAMBAH MATERI BARU (FILE ATAU LINK)
     */
    public function store(Request $request)
    {
        $request->validate([
            'e_product_id' => 'required|exists:e_products,id',
            'title'        => 'required|string|max:255',
            'type'         => 'required|in:file,link',
            // 🔥 Validasi Maksimal 50MB (51200 KB) 🔥
            'file'         => 'required_if:type,file|nullable|file|mimes:pdf,zip,rar|max:51200', 
            'link'         => 'required_if:type,link|nullable|url'
        ], [
            'file.max' => 'Ukuran file terlalu besar! Maksimal 50MB.',
            'file.mimes' => 'Format file harus berupa PDF, ZIP, atau RAR.',
        ]);

        $product = \App\Models\EProduct::findOrFail($request->e_product_id);
        if ($request->user()->role === 'creator' && $product->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $data = $request->only(['e_product_id', 'title', 'type']);

        if ($request->type === 'file' && $request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('e_products/materials', 'public');
        } elseif ($request->type === 'link') {
            $data['link_url'] = $request->link;
        }

        $material = EProductMaterial::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Materi berhasil ditambahkan!',
            'data'    => $material
        ]);
    }

    /**
     * HAPUS MATERI
     */
    public function destroy(Request $request, $id)
    {
        $material = EProductMaterial::findOrFail($id);
        
        $product = \App\Models\EProduct::findOrFail($material->e_product_id);
        if ($request->user()->role === 'creator' && $product->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Jika file fisik, hapus dari storage
        if ($material->type === 'file' && $material->file_path) {
            if (Storage::disk('public')->exists($material->file_path)) {
                Storage::disk('public')->delete($material->file_path);
            }
        }

        $material->delete();

        return response()->json([
            'success' => true,
            'message' => 'Materi berhasil dihapus!'
        ]);
    }
}