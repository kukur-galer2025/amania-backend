<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\EProduct;
use App\Models\EProductPurchase;
use App\Models\EProductReview;
use App\Models\User;
use Faker\Factory as Faker;

class EProductSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        // 1. Pastikan ada user pembuat produk (Admin/Author)
        $author = User::firstOrCreate(
            ['email' => 'admin@amania.id'],
            [
                'name' => 'Admin Amania',
                'password' => bcrypt('password'),
                // Sesuaikan jika Anda menggunakan Spatie Permission atau kolom string biasa untuk role
                'role' => 'superadmin' 
            ]
        );

        // 2. Buat beberapa user dummy sebagai pembeli
        $buyers = [];
        for ($i = 1; $i <= 5; $i++) {
            $buyers[] = User::firstOrCreate(
                ['email' => "pembeli{$i}@gmail.com"],
                [
                    'name' => $faker->name,
                    'password' => bcrypt('password'),
                    'role' => 'user'
                ]
            );
        }

        // 3. Daftar Produk Digital (E-Product)
        $productsData = [
            [
                'title' => 'E-Book Masterclass Lolos CPNS & Kedinasan 2026',
                'description' => 'Panduan komprehensif berisi trik cepat menjawab soal TWK, TIU, dan TKP. Dilengkapi dengan rangkuman materi dari FR (Field Report) tahun-tahun sebelumnya yang sering keluar.',
                'price' => 85000,
            ],
            [
                'title' => 'Bundle Template CV & Surat Lamaran ATS Friendly',
                'description' => 'Kumpulan 20+ template CV dan Surat Lamaran Kerja (Bahasa Indonesia & Inggris) berstandar ATS yang dijamin memperbesar peluang Anda lolos screening HRD BUMN dan Perusahaan Multinasional.',
                'price' => 45000,
            ],
            [
                'title' => 'Video Rekaman Webinar: Rahasia Interview Kerja',
                'description' => 'Akses eksklusif rekaman webinar 3 jam membedah cara menjawab pertanyaan jebakan HRD saat wawancara kerja, lengkap dengan studi kasus.',
                'price' => 125000,
            ],
            [
                'title' => 'Checklist Persiapan Berkas CPNS (Edisi Gratis)',
                'description' => 'Dokumen PDF berisi checklist lengkap persyaratan dokumen yang wajib disiapkan sebelum portal SSCASN dibuka. Jangan sampai gagal seleksi administrasi!',
                'price' => 0, // Produk Gratis
            ]
        ];

        // Eksekusi Pembuatan Data
        foreach ($productsData as $data) {
            // Buat E-Product
            $product = EProduct::create([
                'user_id'      => $author->id,
                'title'        => $data['title'],
                'slug'         => Str::slug($data['title'] . '-' . Str::random(5)),
                'description'  => '<p>' . $data['description'] . '</p>',
                'price'        => $data['price'],
                'cover_image'  => null, // Kosongkan dulu, atau isi dengan path gambar jika sudah ada

                'is_published' => true,
            ]);

        }
    }
}