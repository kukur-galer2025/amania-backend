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

    /**
     * 🔥 Kompres & Resize gambar fisik yang SUDAH ADA di server 🔥
     * 
     * @param string $relativePath Path relatif file saat ini (contoh: 'courses/thumbnails/xyz.jpg')
     * @param int    $maxW         Lebar maksimum (default: 1200px)
     * @param int    $maxH         Tinggi maksimum (default: 900px)
     * @param int    $quality      Kualitas kompresi JPEG/WebP (1-100, default: 80)
     * @return string|null         Path relatif baru (jika format berubah), atau path lama jika sukses
     */
    public static function compressExisting(
        string $relativePath,
        int $maxW = 1200, 
        int $maxH = 900, 
        int $quality = 80
    ): ?string {
        try {
            $fullPath = storage_path('app/public/' . $relativePath);

            if (!file_exists($fullPath)) {
                return $relativePath; // File tidak ada, kembalikan nama aslinya saja
            }

            // Jangan kompres ulang jika sudah WebP atau jika ukurannya sudah sangat kecil (< 200KB)
            if (pathinfo($fullPath, PATHINFO_EXTENSION) === 'webp' || filesize($fullPath) < 200000) {
                return $relativePath;
            }

            $mime = mime_content_type($fullPath);
            
            // Buat resource GD dari file lama
            $source = match ($mime) {
                'image/jpeg', 'image/jpg' => imagecreatefromjpeg($fullPath),
                'image/png'               => imagecreatefrompng($fullPath),
                'image/gif'               => imagecreatefromgif($fullPath),
                default                   => null,
            };

            if (!$source) {
                return $relativePath; // Format tidak didukung, biarkan saja
            }

            $origW = imagesx($source);
            $origH = imagesy($source);

            // Hitung dimensi baru (menjaga aspek rasio)
            $ratio = min($maxW / $origW, $maxH / $origH, 1);
            $newW = (int) round($origW * $ratio);
            $newH = (int) round($origH * $ratio);

            // Buat canvas baru
            $resized = imagecreatetruecolor($newW, $newH);

            if (in_array($mime, ['image/png', 'image/gif'])) {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefill($resized, 0, 0, $transparent);
            }

            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

            $folder = dirname($relativePath);
            
            // Simpan sebagai WebP (menggantikan file lama)
            if (function_exists('imagewebp')) {
                $newFilename = pathinfo($relativePath, PATHINFO_FILENAME) . '.webp';
                $newRelativePath = ($folder === '.' ? '' : $folder . '/') . $newFilename;
                $newFullPath = storage_path('app/public/' . $newRelativePath);
                
                imagewebp($resized, $newFullPath, $quality);
                
                // Hapus file lama jika ekstensi berubah
                if ($newRelativePath !== $relativePath && file_exists($fullPath)) {
                    unlink($fullPath);
                }
                
                $relativePath = $newRelativePath;
            } else {
                // Fallback ke JPEG
                imagejpeg($resized, $fullPath, $quality);
            }

            imagedestroy($source);
            imagedestroy($resized);

            return $relativePath;

        } catch (\Exception $e) {
            \Log::warning('ImageHelper compressExisting failed on ' . $relativePath . ': ' . $e->getMessage());
            return $relativePath;
        }
    }
}
