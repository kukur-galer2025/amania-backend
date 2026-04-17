<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Event;
use App\Models\EventBankAccount;
use App\Models\User;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Pastikan ada User (Organizer/Superadmin) untuk menjadi relasi
        $organizer = User::firstOrCreate(
            ['email' => 'admin@amania.id'],
            [
                'name' => 'Admin Amania Official',
                'password' => bcrypt('password123'),
                // Jika Mas Prima punya kolom role di tabel users, uncomment baris di bawah:
                // 'role' => 'superadmin' 
            ]
        );

        // 2. Daftar Event Dummy (HTML Description disesuaikan dengan Frontend)
        $events = [
            [
                'title' => 'Masterclass Fullstack Web Development (Next.js 15 & Laravel 11)',
                'description' => '<p>Pelajari cara membangun aplikasi modern tingkat <strong>enterprise</strong> dengan kombinasi stack terpopuler saat ini. Masterclass ini mencakup studi kasus pembuatan sistem <em>e-learning</em> berskala besar.</p><h3>Yang Akan Anda Pelajari:</h3><ul><li>Setup Next.js 15 App Router & Server Actions</li><li>Membangun API Cepat & Aman dengan Laravel 11</li><li>Autentikasi & State Management Tingkat Lanjut</li></ul>',
                'venue' => 'Zoom Meeting & Amania Interactive Class',
                'start_time' => Carbon::now()->addDays(7)->setTime(19, 0), // 7 Hari lagi (Akan Datang)
                'end_time' => Carbon::now()->addDays(7)->setTime(21, 30),
                'quota' => 150,
                'basic_price' => 0, // Tiket Basic GRATIS
                'premium_price' => 99000, // Tersedia Upgrade VIP
                'certificate_link' => null,
                'certificate_tier' => 'both',
                'image' => null, // Biarkan null, frontend sudah punya fallback icon
            ],
            [
                'title' => 'Bootcamp Intensif Persiapan CPNS & Sekolah Kedinasan 2026',
                'description' => '<p>Bootcamp 30 hari super intensif untuk mempersiapkan diri menghadapi seleksi SKD CPNS dan Sekolah Kedinasan tahun 2026. Dibimbing langsung oleh ASN berpengalaman dan lulusan terbaik STAN/IPDN.</p><h3>Fasilitas Eksklusif:</h3><ul><li>Akses Rekaman Video Selamanya</li><li>Grup Diskusi & Mentoring VIP</li><li>Tryout Sistem CAT Nasional</li></ul>',
                'venue' => 'Private Google Meet (Link on Dashboard)',
                'start_time' => Carbon::now()->addDays(14)->setTime(8, 0), // 14 Hari lagi (Akan Datang)
                'end_time' => Carbon::now()->addMonths(1)->setTime(17, 0),
                'quota' => 50, // Kuota sedikit
                'basic_price' => 199000, // Berbayar penuh
                'premium_price' => 349000, 
                'certificate_link' => null,
                'certificate_tier' => 'premium',
                'image' => null,
            ],
            [
                'title' => 'Webinar Strategi Jitu Lolos Rekrutmen Bersama BUMN (RBB)',
                'description' => '<p>Kupas tuntas rahasia lolos tahapan seleksi BUMN tahun ini langsung bersama praktisi HRD. Mulai dari bedah CV ATS Friendly, LGD (Leaderless Group Discussion), hingga simulasi Wawancara Direksi.</p>',
                'venue' => 'Live Streaming YouTube Amania',
                'start_time' => Carbon::now()->subDays(3)->setTime(13, 0), // 3 Hari yang lalu (SUDAH SELESAI)
                'end_time' => Carbon::now()->subDays(3)->setTime(15, 0),
                'quota' => 500,
                'basic_price' => 0, // 100% Gratis
                'premium_price' => 0,
                'certificate_link' => 'https://drive.google.com/drive/folders/contoh-sertifikat',
                'certificate_tier' => 'basic',
                'image' => null,
            ],
        ];

        // 3. Masukkan Event ke Database dan Buat Relasi Rekening Bank
        foreach ($events as $eventData) {
            // Generate Slug Otomatis dari Title
            $eventData['slug'] = Str::slug($eventData['title']) . '-' . rand(100, 999);
            $eventData['user_id'] = $organizer->id;

            $event = Event::create($eventData);

            // 4. Jika Event berbayar, otomatis tambahkan Nomor Rekening (EventBankAccount)
            if ($event->basic_price > 0 || $event->premium_price > 0) {
                EventBankAccount::create([
                    'event_id'       => $event->id,
                    'bank_code'      => 'BCA',
                    'account_number' => '1234567890',
                    'account_holder' => 'PT Amania Edukasi Nusantara',
                ]);
                
                EventBankAccount::create([
                    'event_id'       => $event->id,
                    'bank_code'      => 'MANDIRI',
                    'account_number' => '0987654321',
                    'account_holder' => 'PT Amania Edukasi Nusantara',
                ]);
            }
        }
    }
}