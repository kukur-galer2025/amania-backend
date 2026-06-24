<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImageHelper
{
    /**
     * 🔥 Kompres & Resize gambar sebelum disimpan ke storage 🔥
     * 
     * @param UploadedFile $file    File gambar yang diupload
     * @param string       $folder  Folder tujuan di storage (misal: 'e_products/covers')
     * @param int          $maxW    Lebar maksimum (default: 1200px)
     * @param int          $maxH    Tinggi maksimum (default: 900px)
     * @param int          $quality Kualitas kompresi JPEG/WebP (1-100, default: 80)
     * @return string|null          Path file yang tersimpan, atau null jika gagal
     */
    public static function compressAndStore(
        UploadedFile $file, 
        string $folder, 
        int $maxW = 1200, 
        int $maxH = 900, 
        int $quality = 80
    ): ?string {
        try {
            $mime = $file->getMimeType();
            
            // Buat resource GD dari file upload
            $source = match ($mime) {
                'image/jpeg', 'image/jpg' => imagecreatefromjpeg($file->getPathname()),
                'image/png'               => imagecreatefrompng($file->getPathname()),
                'image/webp'              => imagecreatefromwebp($file->getPathname()),
                'image/gif'               => imagecreatefromgif($file->getPathname()),
                default                   => null,
            };

            if (!$source) {
                // Fallback: simpan tanpa kompresi jika format tidak didukung
                return $file->store($folder, 'public');
            }

            $origW = imagesx($source);
            $origH = imagesy($source);

            // Hitung dimensi baru (menjaga aspek rasio)
            $ratio = min($maxW / $origW, $maxH / $origH, 1);
            $newW = (int) round($origW * $ratio);
            $newH = (int) round($origH * $ratio);

            // Buat canvas baru dengan dimensi yang sudah diresize
            $resized = imagecreatetruecolor($newW, $newH);

            // Preserve transparency untuk PNG/WebP
            if (in_array($mime, ['image/png', 'image/webp', 'image/gif'])) {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefill($resized, 0, 0, $transparent);
            }

            // Resize dengan resampling berkualitas tinggi
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

            // Simpan sebagai WebP (ukuran terkecil) dengan fallback ke JPEG
            $extension = 'webp';
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $storagePath = $folder . '/' . $filename;
            $fullPath = storage_path('app/public/' . $storagePath);

            // Pastikan folder ada
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Coba simpan sebagai WebP, fallback ke JPEG
            if (function_exists('imagewebp')) {
                imagewebp($resized, $fullPath, $quality);
            } else {
                $extension = 'jpg';
                $filename = uniqid() . '_' . time() . '.' . $extension;
                $storagePath = $folder . '/' . $filename;
                $fullPath = storage_path('app/public/' . $storagePath);
                imagejpeg($resized, $fullPath, $quality);
            }

            // Bersihkan memori
            imagedestroy($source);
            imagedestroy($resized);

            return $storagePath;

        } catch (\Exception $e) {
            \Log::warning('ImageHelper compress failed: ' . $e->getMessage());
            // Fallback: simpan tanpa kompresi
            return $file->store($folder, 'public');
        }
    }
}
