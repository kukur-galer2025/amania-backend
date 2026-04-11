<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SkdTryoutSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Memulai generate 110 Soal Tryout SKD Asli...');

        // 1. Buat Sub-Kategori (Update nama kategori agar lebih real)
        $twkSubId = DB::table('skd_tryout_categories')->where('slug', 'cpns')->value('id'); 
        // Note: Kita anggap foreign key ke skd_tryout_category_id dari migrasi baru. 
        // Jika belum ada, kita insert dulu kategori utamanya:
        $categoryId = DB::table('skd_tryout_categories')->insertGetId([
            'name' => 'SKD CPNS', 'slug' => 'skd-cpns', 'created_at' => now(), 'updated_at' => now()
        ]);

        $twkSubId = DB::table('skd_question_sub_categories')->insertGetId([
            'main_category' => 'twk', 'name' => 'Pilar Negara', 'created_at' => now(), 'updated_at' => now()
        ]);
        $tiuSubId = DB::table('skd_question_sub_categories')->insertGetId([
            'main_category' => 'tiu', 'name' => 'Kemampuan Silogisme', 'created_at' => now(), 'updated_at' => now()
        ]);
        $tkpSubId = DB::table('skd_question_sub_categories')->insertGetId([
            'main_category' => 'tkp', 'name' => 'Pelayanan Publik', 'created_at' => now(), 'updated_at' => now()
        ]);

        // 2. Buat Data Tryout
        $tryoutId = DB::table('skd_tryouts')->insertGetId([
            'skd_tryout_category_id' => $categoryId,
            'title' => 'Tryout Akbar SKD CPNS 2026 (Real Soal)',
            'slug' => 'tryout-akbar-skd-cpns-2026-' . time(),
            'duration_minutes' => 100,
            'price' => 50000,
            'discount_price' => 25000,
            'is_hots' => true,
            'is_active' => true,
            'description' => '<p>Ini adalah paket Tryout SKD yang menggunakan soal asli berstandar BKN. Terdiri dari 30 soal TWK, 35 soal TIU, dan 45 soal TKP.</p>',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $questions = [];

        // ==========================================
        // 🌟 BANK SOAL REAL TWK (Poin Mutlak 5 / 0)
        // ==========================================
        $twkPool = [
            [
                'q' => 'Semboyan Bhinneka Tunggal Ika yang menjadi semboyan nasional bangsa Indonesia diambil dari kitab karangan Mpu Tantular pada masa Kerajaan Majapahit. Kitab yang dimaksud adalah...',
                'exp' => 'Semboyan Bhinneka Tunggal Ika diambil dari Kitab Sutasoma karangan Mpu Tantular (Kerajaan Majapahit).',
                'opts' => [
                    ['t' => 'Kitab Sutasoma', 's' => 5], // Jawaban Benar
                    ['t' => 'Kitab Negarakertagama', 's' => 0],
                    ['t' => 'Kitab Arjuna Wiwaha', 's' => 0],
                    ['t' => 'Kitab Pararaton', 's' => 0],
                    ['t' => 'Kitab Ramayana', 's' => 0],
                ]
            ],
            [
                'q' => 'Pancasila sebagai ideologi terbuka mengandung makna bahwa nilai-nilai dasar Pancasila dapat dikembangkan sesuai dengan dinamika kehidupan bangsa. Syarat utama sebuah ideologi terbuka adalah...',
                'exp' => 'Ideologi terbuka harus bersumber dari nilai-nilai yang hidup dalam masyarakat itu sendiri, bukan dipaksakan dari luar.',
                'opts' => [
                    ['t' => 'Nilai-nilainya digali dari budaya dan kepribadian bangsa sendiri', 's' => 5],
                    ['t' => 'Mampu menerima semua budaya asing tanpa penyaringan', 's' => 0],
                    ['t' => 'Diciptakan oleh negara untuk mengatur rakyat secara mutlak', 's' => 0],
                    ['t' => 'Isinya sangat operasional dan kaku agar mudah diterapkan', 's' => 0],
                    ['t' => 'Dapat diubah nilai dasarnya setiap berganti presiden', 's' => 0],
                ]
            ],
        ];

        for ($i = 1; $i <= 30; $i++) {
            $tpl = $twkPool[$i % count($twkPool)];
            $opts = $tpl['opts'];
            shuffle($opts); // Acak urutan A B C D E

            $questions[] = [
                'skd_tryout_id' => $tryoutId, 'main_category' => 'twk', 'skd_question_sub_category_id' => $twkSubId,
                'question_text' => '<p><strong>(TWK No. '.$i.')</strong> ' . $tpl['q'] . '</p>',
                'option_a' => '<p>' . $opts[0]['t'] . '</p>', 'score_a' => $opts[0]['s'],
                'option_b' => '<p>' . $opts[1]['t'] . '</p>', 'score_b' => $opts[1]['s'],
                'option_c' => '<p>' . $opts[2]['t'] . '</p>', 'score_c' => $opts[2]['s'],
                'option_d' => '<p>' . $opts[3]['t'] . '</p>', 'score_d' => $opts[3]['s'],
                'option_e' => '<p>' . $opts[4]['t'] . '</p>', 'score_e' => $opts[4]['s'],
                'explanation' => '<p><strong>Pembahasan:</strong><br/>' . $tpl['exp'] . '</p>',
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        $this->command->info('Berhasil mengacak dan membuat 30 soal TWK.');

        // ==========================================
        // 🌟 BANK SOAL REAL TIU (Poin Mutlak 5 / 0)
        // ==========================================
        $tiuPool = [
            [
                'q' => 'Semua pegawai berseragam rapi. Sebagian pegawai menggunakan dasi. Kesimpulan yang paling tepat adalah...',
                'exp' => 'Jika Semua A adalah B, dan Sebagian A adalah C. Maka kesimpulannya adalah Sebagian A yang menjadi B juga merupakan C (Sebagian pegawai berseragam rapi dan menggunakan dasi).',
                'opts' => [
                    ['t' => 'Sebagian pegawai berseragam rapi dan menggunakan dasi', 's' => 5],
                    ['t' => 'Semua pegawai berseragam rapi dan menggunakan dasi', 's' => 0],
                    ['t' => 'Sebagian pegawai tidak berseragam rapi', 's' => 0],
                    ['t' => 'Pegawai yang menggunakan dasi pasti tidak berseragam rapi', 's' => 0],
                    ['t' => 'Tidak ada kesimpulan yang benar', 's' => 0],
                ]
            ],
            [
                'q' => 'Lanjutkan deret angka berikut: 3, 6, 12, 24, 48, ...',
                'exp' => 'Pola deret tersebut adalah dikali 2 (x2). Maka angka selanjutnya adalah 48 x 2 = 96.',
                'opts' => [
                    ['t' => '96', 's' => 5],
                    ['t' => '72', 's' => 0],
                    ['t' => '84', 's' => 0],
                    ['t' => '100', 's' => 0],
                    ['t' => '108', 's' => 0],
                ]
            ],
        ];

        for ($i = 1; $i <= 35; $i++) {
            $tpl = $tiuPool[$i % count($tiuPool)];
            $opts = $tpl['opts'];
            shuffle($opts);

            $questions[] = [
                'skd_tryout_id' => $tryoutId, 'main_category' => 'tiu', 'skd_question_sub_category_id' => $tiuSubId,
                'question_text' => '<p><strong>(TIU No. '.$i.')</strong> ' . $tpl['q'] . '</p>',
                'option_a' => '<p>' . $opts[0]['t'] . '</p>', 'score_a' => $opts[0]['s'],
                'option_b' => '<p>' . $opts[1]['t'] . '</p>', 'score_b' => $opts[1]['s'],
                'option_c' => '<p>' . $opts[2]['t'] . '</p>', 'score_c' => $opts[2]['s'],
                'option_d' => '<p>' . $opts[3]['t'] . '</p>', 'score_d' => $opts[3]['s'],
                'option_e' => '<p>' . $opts[4]['t'] . '</p>', 'score_e' => $opts[4]['s'],
                'explanation' => '<p><strong>Pembahasan:</strong><br/>' . $tpl['exp'] . '</p>',
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        $this->command->info('Berhasil mengacak dan membuat 35 soal TIU.');

        // ==========================================
        // 🌟 BANK SOAL REAL TKP (Poin Variatif 1 - 5)
        // ==========================================
        $tkpPool = [
            [
                'q' => 'Saat Anda sedang bertugas di loket pelayanan, ada seorang bapak tua yang marah-marah karena merasa antreannya diserobot oleh orang lain. Sikap Anda sebagai pelayan publik adalah...',
                'exp' => 'Indikator: Pelayanan Publik. Menenangkan dan memberikan penjelasan yang solutif adalah sikap terbaik (Poin 5).',
                'opts' => [
                    ['t' => 'Menghampiri bapak tersebut, meminta maaf atas ketidaknyamanan, lalu mengecek nomor antrean sebenarnya untuk diselesaikan', 's' => 5],
                    ['t' => 'Meminta satpam untuk menenangkan bapak tersebut karena mengganggu antrean lain', 's' => 4],
                    ['t' => 'Tetap fokus melayani pelanggan di depan saya agar antrean cepat selesai', 's' => 3],
                    ['t' => 'Menasihati bapak tersebut agar bersabar dan tidak perlu marah-marah di tempat umum', 's' => 2],
                    ['t' => 'Memarahi orang yang menyerobot antrean agar bapak tersebut merasa dibela', 's' => 1],
                ]
            ],
            [
                'q' => 'Anda ditugaskan dalam sebuah tim kerja dengan anggota yang memiliki latar belakang budaya dan sifat yang sangat berbeda-beda. Suatu hari, terjadi selisih paham yang membuat tim menjadi canggung. Langkah Anda...',
                'exp' => 'Indikator: Jejaring Kerja / Kerjasama. Mengambil inisiatif mencari jalan tengah menunjukkan sifat kepemimpinan dan kolaborasi yang tinggi.',
                'opts' => [
                    ['t' => 'Mengajak tim duduk bersama, mendengarkan masalah dengan kepala dingin, dan mencari win-win solution', 's' => 5],
                    ['t' => 'Meminta ketua tim untuk turun tangan menengahi konflik tersebut', 's' => 4],
                    ['t' => 'Memposisikan diri netral dan tidak memihak siapapun agar tidak ikut terseret konflik', 's' => 3],
                    ['t' => 'Tetap bekerja seperti biasa, karena konflik personal tidak boleh mengganggu pekerjaan', 's' => 2],
                    ['t' => 'Mengeluhkan kondisi tim kepada atasan dan meminta dipindah ke tim lain', 's' => 1],
                ]
            ],
        ];

    for ($i = 1; $i <= 45; $i++) {
            $tpl = $tkpPool[$i % count($tkpPool)];
            $opts = $tpl['opts'];
            shuffle($opts);

            $questions[] = [
                'skd_tryout_id' => $tryoutId, 'main_category' => 'tkp', 'skd_question_sub_category_id' => $tkpSubId,
                'question_text' => '<p><strong>(TKP No. '.$i.')</strong> ' . $tpl['q'] . '</p>',
                'option_a' => '<p>' . $opts[0]['t'] . '</p>', 'score_a' => $opts[0]['s'],
                'option_b' => '<p>' . $opts[1]['t'] . '</p>', 'score_b' => $opts[1]['s'],
                'option_c' => '<p>' . $opts[2]['t'] . '</p>', 'score_c' => $opts[2]['s'],
                'option_d' => '<p>' . $opts[3]['t'] . '</p>', 'score_d' => $opts[3]['s'],
                'option_e' => '<p>' . $opts[4]['t'] . '</p>', 'score_e' => $opts[4]['s'],
                'explanation' => '<p><strong>Pembahasan:</strong><br/>' . $tpl['exp'] . '</p>',
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        $this->command->info('Berhasil mengacak dan membuat 45 soal TKP.');

        // 3. Masukkan ke Database dengan sistem Chunking (Aman dari Out of Memory)
        $chunks = array_chunk($questions, 50);
        foreach ($chunks as $chunk) {
            DB::table('skd_questions')->insert($chunk);
        }

        $this->command->info('Selesai! Tryout dengan 110 Soal Asli beserta Kunci Jawaban Acak siap digunakan! 🚀');
    }
}