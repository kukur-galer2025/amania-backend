<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use App\Models\Article;
use App\Models\Course;
use App\Models\EProduct;
use App\Models\User;

class ConvertImagesToWebp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'amania:convert-webp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert all existing images (JPG/PNG) to WebP and update the database.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Memulai proses konversi gambar ke WebP...");

        // 1. Articles
        $this->info("Memproses Artikel...");
        $articles = Article::all();
        foreach ($articles as $article) {
            if ($article->image) {
                $newPath = $this->convertToWebp($article->image);
                if ($newPath && $newPath !== $article->image) {
                    $article->image = $newPath;
                    $article->save();
                    $this->line("Converted: {$newPath}");
                }
            }
        }

        // 2. Courses
        $this->info("Memproses Kursus...");
        $courses = Course::all();
        foreach ($courses as $course) {
            if ($course->thumbnail) {
                $newPath = $this->convertToWebp($course->thumbnail);
                if ($newPath && $newPath !== $course->thumbnail) {
                    $course->thumbnail = $newPath;
                    $course->save();
                    $this->line("Converted: {$newPath}");
                }
            }
        }

        // 3. E-Products
        $this->info("Memproses E-Produk...");
        $products = EProduct::all();
        foreach ($products as $product) {
            if ($product->cover_image) {
                $newPath = $this->convertToWebp($product->cover_image);
                if ($newPath && $newPath !== $product->cover_image) {
                    $product->cover_image = $newPath;
                    $product->save();
                    $this->line("Converted: {$newPath}");
                }
            }
        }

        // 4. Users (Avatar)
        $this->info("Memproses Avatar User...");
        $users = User::all();
        foreach ($users as $user) {
            if ($user->avatar && !filter_var($user->avatar, FILTER_VALIDATE_URL)) {
                $newPath = $this->convertToWebp($user->avatar);
                if ($newPath && $newPath !== $user->avatar) {
                    $user->avatar = $newPath;
                    $user->save();
                    $this->line("Converted: {$newPath}");
                }
            }
        }

        $this->info("Selesai! Semua gambar berhasil dikonversi ke WebP.");
    }

    /**
     * Convert an image to WebP and delete the old one.
     */
    private function convertToWebp($oldPath)
    {
        // Skip if already webp
        if (strtolower(pathinfo($oldPath, PATHINFO_EXTENSION)) === 'webp') {
            return $oldPath;
        }

        $disk = Storage::disk('public');

        if (!$disk->exists($oldPath)) {
            return null; // File not found
        }

        $fullPath = $disk->path($oldPath);
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if (!in_array($extension, ['jpg', 'jpeg', 'png'])) {
            return $oldPath; // Only convert jpg/png
        }

        // Load image using GD
        $image = null;
        if ($extension === 'png') {
            $image = @imagecreatefrompng($fullPath);
            if ($image) {
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
            }
        } else {
            $image = @imagecreatefromjpeg($fullPath);
        }

        if (!$image) {
            $this->error("Gagal membaca gambar: {$oldPath}");
            return $oldPath;
        }

        // Generate new path
        $newPath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $oldPath);
        $newFullPath = $disk->path($newPath);

        // Save as WebP (Quality: 80)
        $success = imagewebp($image, $newFullPath, 80);
        imagedestroy($image);

        if ($success) {
            // Delete old file
            $disk->delete($oldPath);
            return $newPath;
        }

        return $oldPath;
    }
}
