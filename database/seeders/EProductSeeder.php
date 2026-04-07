<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EProduct;
use App\Models\User;
use Illuminate\Support\Str;

class EProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Cari user Superadmin untuk dijadikan sebagai penulis/pembuat E-Produk
        // Sesuaikan dengan data yang ada di database kamu, atau buat baru kalau kosong.
        $admin = User::where('role', 'superadmin')->first();

        if (!$admin) {
            $admin = User::create([
                'name' => 'Super Administrator',
                'email' => 'admin@amania.id',
                'password' => bcrypt('password123'),
                'role' => 'superadmin'
            ]);
        }

        // 2. Siapkan data dummy E-Produk
        $products = [
            [
                'title' => 'Masterclass Fullstack Web Development (Next.js & Laravel)',
                'description' => '<h3>Modul Komprehensif Siap Kerja!</h3><p>Pelajari cara membangun platform berskala Enterprise seperti Amania dari nol hingga deploy. Dilengkapi dengan <strong>source code eksklusif</strong> dan grup diskusi privat.</p><ul><li>Membangun API Cepat dengan Laravel 11</li><li>Frontend interaktif dengan Next.js App Router</li><li>Integrasi Midtrans Payment Gateway</li></ul>',
                'price' => 150000,
                'cover_image' => null, // Dibiarkan null agar Frontend menggunakan icon default
                'file_path' => 'e_products/files/dummy-module.pdf', // File bohongan
                'is_published' => true,
            ],
            [
                'title' => 'E-Book: Rahasia Karir UI/UX Designer',
                'description' => '<p>Buku digital ini membahas langkah demi langkah membangun portofolio UI/UX yang dilirik oleh HRD tech company ternama. Berisi template CV, studi kasus nyata, dan cara menjawab interview design.</p>',
                'price' => 75000,
                'cover_image' => null,
                'file_path' => 'e_products/files/dummy-ebook.pdf',
                'is_published' => true,
            ],
            [
                'title' => 'Template Notion: Manajemen Proyek Organizer',
                'description' => '<p>Tingkatkan produktivitas tim event kamu dengan template Notion eksklusif yang biasa digunakan oleh Top Event Organizer. <strong>100% GRATIS!</strong></p>',
                'price' => 0, // 🔥 Produk Gratis untuk testing bypass Midtrans
                'cover_image' => null,
                'file_path' => 'e_products/files/dummy-notion.pdf',
                'is_published' => true,
            ]
        ];

        // 3. Masukkan ke Database
        foreach ($products as $item) {
            EProduct::create([
                'user_id' => $admin->id,
                'title' => $item['title'],
                'slug' => Str::slug($item['title']) . '-' . uniqid(),
                'description' => $item['description'],
                'price' => $item['price'],
                'cover_image' => $item['cover_image'],
                'file_path' => $item['file_path'],
                'is_published' => $item['is_published'],
            ]);
        }

        $this->command->info('E-Product Seeder berhasil dijalankan! 3 Produk Dummy telah ditambahkan.');
    }
}