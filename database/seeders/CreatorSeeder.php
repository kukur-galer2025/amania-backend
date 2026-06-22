<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Course;
use App\Models\EProduct;
use App\Models\CourseEnrollment;
use App\Models\EProductPurchase;
use App\Models\EProductOrderItem;
use App\Models\Article;
use Illuminate\Support\Str;

class CreatorSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Kreator
        $creator = User::firstOrCreate(
            ['email' => 'creator@amania.com'],
            [
                'name' => 'Akun Kreator',
                'password' => Hash::make('password'),
                'role' => 'creator',
            ]
        );

        // 2. Akun Pembeli Dummy
        $buyer = User::firstOrCreate(
            ['email' => 'buyer@amania.com'],
            [
                'name' => 'Budi Pembeli',
                'password' => Hash::make('password'),
                'role' => 'user',
            ]
        );

        // 3. E-Produk milik Kreator
        $eproduct = EProduct::firstOrCreate(
            ['slug' => 'ebook-jago-jualan'],
            [
                'user_id' => $creator->id,
                'title' => 'Ebook Jago Jualan 2026',
                'description' => 'Buku panduan lengkap.',
                'price' => 150000,
                'is_published' => true,
            ]
        );

        // 4. Kursus milik Kreator
        $course = Course::firstOrCreate(
            ['slug' => 'masterclass-digital-marketing'],
            [
                'user_id' => $creator->id,
                'title' => 'Masterclass Digital Marketing',
                'description' => 'Kursus lengkap dari nol sampai jago.',
                'price' => 350000,
                'level' => 'beginner',
                'is_published' => true,
            ]
        );

        // 5. Transaksi E-Produk
        if (EProductPurchase::where('user_id', $buyer->id)->count() === 0) {
            $purchase = EProductPurchase::create([
                'user_id' => $buyer->id,
                'reference' => 'INV-EP-' . Str::random(6),
                'amount' => 150000,
                'status' => 'UNPAID',
                'payment_method' => 'QRIS MANUAL'
            ]);

            EProductOrderItem::create([
                'e_product_purchase_id' => $purchase->id,
                'e_product_id' => $eproduct->id,
                'price' => 150000,
            ]);
        }

        // 6. Transaksi Kursus
        if (CourseEnrollment::where('user_id', $buyer->id)->where('course_id', $course->id)->count() === 0) {
            CourseEnrollment::create([
                'user_id' => $buyer->id,
                'course_id' => $course->id,
                'reference' => 'INV-CRS-' . Str::random(6),
                'amount' => 350000,
                'status' => 'UNPAID',
                'payment_method' => 'QRIS MANUAL'
            ]);
        }

        // 7. Kategori Artikel Dummy
        $category = \App\Models\ArticleCategory::firstOrCreate(
            ['slug' => 'umum'],
            ['name' => 'Umum']
        );

        // 8. Artikel milik Kreator
        Article::firstOrCreate(
            ['slug' => 'tips-sukses-kreator-2026'],
            [
                'user_id' => $creator->id,
                'article_category_id' => $category->id,
                'title' => 'Tips Sukses Jadi Kreator 2026',
                'content' => '<p>Halo semuanya! Ini adalah artikel pertama dari seorang kreator. Belajar terus pantang mundur.</p>',
                'read_time' => 3,
                'is_published' => true,
            ]
        );
    }
}
