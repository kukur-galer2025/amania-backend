<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EProduct;
use App\Models\Course;
use App\Models\Event;
use App\Models\Article;
use App\Models\Advertisement;
use App\Helpers\ImageHelper;
use Illuminate\Support\Str;

class CompressExistingImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:compress';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compress all existing large images in storage (E-Products, Courses, Events, Articles, Ads) to WebP format.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🚀 Memulai proses kompresi gambar lama...");

        // 1. E-Products
        $this->info("-------------------------------------------------");
        $this->info("📦 Mengkompresi Cover E-Product...");
        $eproducts = EProduct::whereNotNull('cover_image')->get();
        $count = 0;
        foreach ($eproducts as $product) {
            if ($this->shouldCompress($product->cover_image)) {
                $newPath = ImageHelper::compressExisting($product->cover_image, 1200, 900, 80);
                if ($newPath !== $product->cover_image) {
                    $product->update(['cover_image' => $newPath]);
                    $count++;
                    $this->line("   ✅ Terkompresi: {$newPath}");
                }
            }
        }
        $this->info("Selesai: {$count} cover E-Product dikompresi.");

        // 2. Courses
        $this->info("-------------------------------------------------");
        $this->info("🎓 Mengkompresi Thumbnail Kursus...");
        $courses = Course::whereNotNull('thumbnail')->get();
        $count = 0;
        foreach ($courses as $course) {
            if ($this->shouldCompress($course->thumbnail)) {
                $newPath = ImageHelper::compressExisting($course->thumbnail, 1200, 900, 80);
                if ($newPath !== $course->thumbnail) {
                    $course->update(['thumbnail' => $newPath]);
                    $count++;
                    $this->line("   ✅ Terkompresi: {$newPath}");
                }
            }
        }
        $this->info("Selesai: {$count} thumbnail kursus dikompresi.");

        // 3. Events
        $this->info("-------------------------------------------------");
        $this->info("📅 Mengkompresi Gambar Event...");
        $events = Event::whereNotNull('image')->get();
        $count = 0;
        foreach ($events as $event) {
            if ($this->shouldCompress($event->image)) {
                $newPath = ImageHelper::compressExisting($event->image, 1200, 900, 80);
                if ($newPath !== $event->image) {
                    $event->update(['image' => $newPath]);
                    $count++;
                    $this->line("   ✅ Terkompresi: {$newPath}");
                }
            }
        }
        $this->info("Selesai: {$count} gambar event dikompresi.");

        // 4. Articles
        $this->info("-------------------------------------------------");
        $this->info("📰 Mengkompresi Gambar Artikel...");
        $articles = Article::whereNotNull('image')->get();
        $count = 0;
        foreach ($articles as $article) {
            if ($this->shouldCompress($article->image)) {
                $newPath = ImageHelper::compressExisting($article->image, 1200, 900, 80);
                if ($newPath !== $article->image) {
                    $article->update(['image' => $newPath]);
                    $count++;
                    $this->line("   ✅ Terkompresi: {$newPath}");
                }
            }
        }
        $this->info("Selesai: {$count} gambar artikel dikompresi.");

        // 5. Advertisements
        $this->info("-------------------------------------------------");
        $this->info("📢 Mengkompresi Gambar Iklan...");
        $ads = Advertisement::whereNotNull('image_path')->get();
        $count = 0;
        foreach ($ads as $ad) {
            if ($this->shouldCompress($ad->image_path)) {
                $newPath = ImageHelper::compressExisting($ad->image_path, 1200, 900, 80);
                if ($newPath !== $ad->image_path) {
                    $ad->update(['image_path' => $newPath]);
                    $count++;
                    $this->line("   ✅ Terkompresi: {$newPath}");
                }
            }
        }
        $this->info("Selesai: {$count} gambar iklan dikompresi.");

        $this->info("-------------------------------------------------");
        $this->info("🎉 SEMUA SELESAI! Gambar lama berhasil dikompresi.");
    }

    /**
     * Cek apakah gambar layak dikompresi (bukan URL eksternal)
     */
    private function shouldCompress(?string $path): bool
    {
        if (!$path) return false;
        if (Str::startsWith($path, ['http://', 'https://'])) return false;
        
        $fullPath = storage_path('app/public/' . $path);
        return file_exists($fullPath);
    }
}
