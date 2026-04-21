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
                'file_path'    => 'dummy/file-' . Str::random(5) . '.pdf',
                'is_published' => true,
            ]);

            // Ambil 3-4 pembeli acak untuk produk ini
            $randomBuyers = $faker->randomElements($buyers, rand(3, 4));

            foreach ($randomBuyers as $buyer) {
                // Tentukan status acak (Banyakan PAID agar ada review-nya)
                $status = $data['price'] == 0 ? 'PAID' : $faker->randomElement(['PAID', 'UNPAID', 'PAID', 'PAID']);

                // Buat Riwayat Pembelian
                EProductPurchase::create([
                    'reference'        => 'INV-EP-' . strtoupper(Str::random(8)) . '-' . $buyer->id,
                    'tripay_reference' => $status === 'PAID' && $data['price'] > 0 ? 'DEV-T' . strtoupper(Str::random(10)) : null,
                    'user_id'          => $buyer->id,
                    'e_product_id'     => $product->id,
                    'amount'           => $data['price'],
                    'checkout_url'     => $status === 'UNPAID' ? 'https://tripay.co.id/checkout/dummy' : null,
                    'status'           => $status,
                ]);

                // Buat Ulasan (Review) HANYA jika statusnya PAID
                if ($status === 'PAID') {
                    EProductReview::create([
                        'e_product_id' => $product->id,
                        'user_id'      => $buyer->id,
                        // Kasih rating bagus (4 atau 5)
                        'rating'       => $faker->numberBetween(4, 5), 
                        'review'       => $faker->sentence(rand(6, 12)) . ' ' . $faker->randomElement(['Sangat bermanfaat!', 'Terima kasih Amania.', 'Mantap materinya.']),
                    ]);
                }
            }
        }
    }
}