<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\EProduct;
use App\Models\EProductPurchase;
use App\Models\EProductReview;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class EProductSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat Dummy Author (Superadmin)
        $admin = User::firstOrCreate(
            ['email' => 'adminku@amania.id'],
            [
                'name' => 'Admin Amania',
                'password' => Hash::make('password123'),
                'role' => 'superadmin'
            ]
        );

        // 2. Buat Dummy Buyer (User)
        $member = User::firstOrCreate(
            ['email' => 'member@amania.id'],
            [
                'name' => 'Member Setia',
                'password' => Hash::make('password123'),
                'role' => 'user' 
            ]
        );

        // 3. Buat Data E-Products
        $products = [
            [
                'user_id'      => $admin->id,
                'title'        => 'Masterclass Fullstack Web Development (Next.js 15 & Laravel 11)',
                'slug'         => Str::slug('Masterclass Fullstack Web Development Next.js Laravel'),
                'description'  => '<p>Pelajari cara membangun aplikasi modern tingkat *enterprise* dengan kombinasi maut Next.js 15 di Frontend dan Laravel 11 di Backend. E-book ini mencakup studi kasus pembuatan sistem e-learning yang skalabel.</p>',
                'price'        => 149000,
                'file_path'    => 'e_products/files/dummy1.pdf', // 🔥 DITAMBAHKAN
                'cover_image'  => null,
                'is_published' => true,
            ],
            [
                'user_id'      => $admin->id,
                'title'        => 'Template UI/UX Figma: Sistem Tryout CPNS VILT Stack',
                'slug'         => Str::slug('Template UI UX Figma Sistem Tryout CPNS'),
                'description'  => '<p>Akselerasi proses desain Anda dengan *template* Figma responsif khusus untuk aplikasi Tryout CAT CPNS. Dilengkapi dengan *design system*, komponen interaktif, dan panduan implementasi ke Vue/Inertia/Tailwind.</p>',
                'price'        => 89000,
                'file_path'    => 'e_products/files/dummy2.zip', // 🔥 DITAMBAHKAN
                'cover_image'  => null,
                'is_published' => true,
            ],
            [
                'user_id'      => $admin->id,
                'title'        => 'Panduan Dasar Jaringan Komputer & Mikrotik',
                'slug'         => Str::slug('Panduan Dasar Jaringan Komputer Mikrotik'),
                'description'  => '<p>E-Book panduan praktis untuk mahasiswa Informatika. Membahas *subnetting*, konfigurasi dasar Cisco IOS, *dynamic routing* (OSPF, RIP), dan manajemen Mikrotik via Winbox secara komprehensif.</p>',
                'price'        => 0, // Produk Gratis
                'file_path'    => 'e_products/files/dummy3.pdf', // 🔥 DITAMBAHKAN
                'cover_image'  => null,
                'is_published' => true,
            ],
        ];

        foreach ($products as $prodData) {
            $product = EProduct::create($prodData);

            // 4. Buat Simulasi Transaksi (Purchase) untuk si Member
            $purchase = EProductPurchase::create([
                'reference'        => 'INV-EP-' . strtoupper(Str::random(8)),
                'tripay_reference' => 'DEV-' . strtoupper(Str::random(10)),
                'user_id'          => $member->id,
                'e_product_id'     => $product->id,
                'amount'           => $product->price,
                'checkout_url'     => 'https://tripay.co.id/checkout/dummy',
                'status'           => 'PAID',
            ]);

            // 5. Beri Ulasan (Review) pada 2 produk pertama
            if ($product->price > 0) {
                EProductReview::create([
                    'e_product_id' => $product->id,
                    'user_id'      => $member->id,
                    'rating'       => 5,
                    'review'       => 'Sangat bermanfaat! Materi terstruktur rapi dan mudah diimplementasikan ke project skripsi/tugas akhir.',
                ]);
            }
        }
    }
}