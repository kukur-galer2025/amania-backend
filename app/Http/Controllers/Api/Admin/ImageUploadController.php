<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ImageUploadController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Maks 2MB
        ]);

        if ($request->hasFile('image')) {
            // Simpan gambar ke folder 'public/questions'
            $path = $request->file('image')->store('questions', 'public');
            
            // Kembalikan URL lengkapnya ke frontend
            return response()->json([
                'success' => true,
                'url' => asset('storage/' . $path)
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Upload gagal, file tidak ditemukan'], 400);
    }
}