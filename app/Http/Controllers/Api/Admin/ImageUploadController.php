<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\ImageHelper;

class ImageUploadController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Maks 2MB
        ]);

        if ($request->hasFile('image')) {
            // Simpan gambar ke folder 'public/questions' dengan kompresi WebP
            $path = ImageHelper::compressAndStore($request->file('image'), 'questions', 1000, 1000, 80);
            
            // Kembalikan URL lengkapnya ke frontend
            return response()->json([
                'success' => true,
                'url' => asset('storage/' . $path)
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Upload gagal, file tidak ditemukan'], 400);
    }
}