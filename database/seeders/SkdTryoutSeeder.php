<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\SkdTryoutCategory;
use App\Models\SkdQuestionSubCategory;
use App\Models\SkdTryout;
use App\Models\SkdQuestion;
use Faker\Factory as Faker;

class SkdTryoutSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        // 1. KATEGORI TRYOUT UTAMA
        $category = SkdTryoutCategory::firstOrCreate(
            ['slug' => 'cpns-2026'],
            ['name' => 'CPNS 2026']
        );

        // 2. SUB-KATEGORI MATERI (SESUAI KISI-KISI BKN)
        // Kumpulan materi TWK
        $twkMateri = [
            SkdQuestionSubCategory::firstOrCreate(['main_category' => 'twk', 'name' => 'Nasionalisme']),
            SkdQuestionSubCategory::firstOrCreate(['main_category' => 'twk', 'name' => 'Integritas']),
            SkdQuestionSubCategory::firstOrCreate(['main_category' => 'twk', 'name' => 'Bela Negara']),
            SkdQuestionSubCategory::firstOrCreate(['main_category' => 'twk', 'name' => 'Pilar Negara']),
        ];

        // Kumpulan materi TIU
        $tiuMateri = [
            SkdQuestionSubCategory::firstOrCreate(['main_category' => 'tiu', 'name' => 'Silogisme']),
            SkdQuestionSubCategory::firstOrCreate(['main_category' => 'tiu', 'name' => 'Analogi']),
            SkdQuestionSubCategory::firstOrCreate(['main_category' => 'tiu', 'name' => 'Deret Angka']),
            SkdQuestionSubCategory::firstOrCreate(['main_category' => 'tiu', 'name' => 'Figural']),
        ];

        // Kumpulan materi TKP
        $tkpMateri = [
            SkdQuestionSubCategory::firstOrCreate(['main_category' => 'tkp', 'name' => 'Pelayanan Publik']),
            SkdQuestionSubCategory::firstOrCreate(['main_category' => 'tkp', 'name' => 'Jejaring Kerja']),
            SkdQuestionSubCategory::firstOrCreate(['main_category' => 'tkp', 'name' => 'Sosial Budaya']),
            SkdQuestionSubCategory::firstOrCreate(['main_category' => 'tkp', 'name' => 'Anti Radikalisme']),
        ];

        // 3. BUAT DATA TRYOUT UTAMA
        $tryout = SkdTryout::create([
            'skd_tryout_category_id' => $category->id,
            'title'                  => 'Simulasi SKD CPNS 2026 Premium (CAT BKN 110 Soal)',
            'slug'                   => Str::slug('Simulasi SKD CPNS 2026 Premium (CAT BKN 110 Soal)'),
            'description'            => 'Tryout ini dirancang khusus menyerupai standar CAT BKN terbaru dengan komposisi baku 110 Soal (30 TWK, 35 TIU, 45 TKP) berstandar HOTS.',
            'duration_minutes'       => 100,
            'price'                  => 149000,
            'discount_price'         => 49000,
            'discount_start_date'    => now()->subDay(),
            'discount_end_date'      => now()->addDays(7),
            'is_hots'                => true,
            'is_active'              => true,
        ]);

        // ========================================================
        // BAGIAN 1: TWK (30 SOAL) -> Poin 5 (Benar) atau 0 (Salah)
        // ========================================================
        for ($i = 1; $i <= 30; $i++) {
            $subCat = $faker->randomElement($twkMateri); // Pilih materi acak

            if ($i === 1) {
                $text = 'Pancasila sebagai ideologi terbuka memiliki dimensi realitas, idealitas, dan fleksibilitas. Di era globalisasi, banyak generasi muda yang lebih mengagumi budaya asing. Tindakan yang paling tepat untuk mengimplementasikan dimensi fleksibilitas Pancasila tanpa menghilangkan jati diri bangsa adalah...';
                $explanation = 'Materi: Pilar Negara. Dimensi fleksibilitas berarti Pancasila mampu menyesuaikan diri dengan perkembangan zaman. Memanfaatkan teknologi luar untuk mempromosikan budaya lokal adalah bentuk adaptasi yang tepat.';
                $ops = [
                    'a' => ['text' => 'Menolak segala bentuk budaya asing yang masuk ke Indonesia.', 'score' => 0],
                    'b' => ['text' => 'Mempelajari budaya asing secara mendalam untuk diakulturasi secara paksa.', 'score' => 0],
                    'c' => ['text' => 'Memanfaatkan teknologi digital saat ini sebagai media untuk mempromosikan kebudayaan lokal ke kancah internasional.', 'score' => 5], // Kunci Jawaban
                    'd' => ['text' => 'Mewajibkan seluruh generasi muda untuk menggunakan pakaian adat.', 'score' => 0],
                    'e' => ['text' => 'Membiarkan budaya asing berkembang asalkan mendatangkan devisa.', 'score' => 0],
                ];
            } else {
                $text = "(Soal TWK - {$subCat->name}) " . $faker->paragraph(2) . " Berdasarkan narasi historis tersebut, manakah sikap yang paling mencerminkan nilai integritas nasional?";
                $explanation = "Pembahasan TWK: " . $faker->sentence(10);
                
                $scores = [5, 0, 0, 0, 0];
                shuffle($scores);
                $ops = [
                    'a' => ['text' => $faker->sentence(6), 'score' => $scores[0]],
                    'b' => ['text' => $faker->sentence(5), 'score' => $scores[1]],
                    'c' => ['text' => $faker->sentence(7), 'score' => $scores[2]],
                    'd' => ['text' => $faker->sentence(6), 'score' => $scores[3]],
                    'e' => ['text' => $faker->sentence(8), 'score' => $scores[4]],
                ];
            }

            SkdQuestion::create([
                'skd_tryout_id'                => $tryout->id,
                'skd_question_sub_category_id' => $subCat->id,
                'main_category'                => 'twk',
                'question_text'                => $text,
                'option_a'                     => $ops['a']['text'], 'score_a' => $ops['a']['score'],
                'option_b'                     => $ops['b']['text'], 'score_b' => $ops['b']['score'],
                'option_c'                     => $ops['c']['text'], 'score_c' => $ops['c']['score'],
                'option_d'                     => $ops['d']['text'], 'score_d' => $ops['d']['score'],
                'option_e'                     => $ops['e']['text'], 'score_e' => $ops['e']['score'],
                'explanation'                  => $explanation,
            ]);
        }

        // ========================================================
        // BAGIAN 2: TIU (35 SOAL) -> Poin 5 (Benar) atau 0 (Salah)
        // ========================================================
        for ($i = 1; $i <= 35; $i++) {
            $subCat = $faker->randomElement($tiuMateri);

            if ($i === 1) { 
                $text = "Semua ASN wajib menjunjung tinggi netralitas pada saat pemilihan umum.\nSebagian anggota keluarga Pak Budi adalah ASN.\n\nKesimpulan yang paling tepat dari dua premis di atas adalah...";
                $explanation = 'Materi: Silogisme. Rumus: Premis 1 (A=B), Premis 2 (C=A). Kesimpulan (C=B): Sebagian keluarga Pak Budi = Wajib Netral.';
                $ops = [
                    'a' => ['text' => 'Semua anggota keluarga Pak Budi wajib menjunjung tinggi netralitas.', 'score' => 0],
                    'b' => ['text' => 'Sebagian anggota keluarga Pak Budi tidak wajib netral.', 'score' => 0],
                    'c' => ['text' => 'Sebagian anggota keluarga Pak Budi wajib menjunjung tinggi netralitas pada saat pemilihan umum.', 'score' => 5], // Kunci
                    'd' => ['text' => 'Pak Budi wajib menjunjung tinggi netralitas.', 'score' => 0],
                    'e' => ['text' => 'Semua ASN adalah anggota keluarga Pak Budi.', 'score' => 0],
                ];
            } else {
                $text = "(Soal TIU - {$subCat->name}) Jika X = " . rand(10, 50) . " dan Y = " . rand(10, 50) . ", maka pernyataan yang tepat adalah " . $faker->sentence(4);
                $explanation = "Pembahasan TIU: Karena nilai X dan Y sudah diketahui, maka perhitungannya adalah...";
                
                $scores = [5, 0, 0, 0, 0];
                shuffle($scores);
                $ops = [
                    'a' => ['text' => 'X > Y', 'score' => $scores[0]],
                    'b' => ['text' => 'X < Y', 'score' => $scores[1]],
                    'c' => ['text' => 'X = Y', 'score' => $scores[2]],
                    'd' => ['text' => 'Hubungan X dan Y tidak dapat ditentukan.', 'score' => $scores[3]],
                    'e' => ['text' => 'X + Y = 100', 'score' => $scores[4]],
                ];
            }

            SkdQuestion::create([
                'skd_tryout_id'                => $tryout->id,
                'skd_question_sub_category_id' => $subCat->id,
                'main_category'                => 'tiu',
                'question_text'                => $text,
                'option_a'                     => $ops['a']['text'], 'score_a' => $ops['a']['score'],
                'option_b'                     => $ops['b']['text'], 'score_b' => $ops['b']['score'],
                'option_c'                     => $ops['c']['text'], 'score_c' => $ops['c']['score'],
                'option_d'                     => $ops['d']['text'], 'score_d' => $ops['d']['score'],
                'option_e'                     => $ops['e']['text'], 'score_e' => $ops['e']['score'],
                'explanation'                  => $explanation,
            ]);
        }

        // ========================================================
        // BAGIAN 3: TKP (45 SOAL) -> Poin 1 sampai 5 (Tidak ada salah)
        // ========================================================
        for ($i = 1; $i <= 45; $i++) {
            $subCat = $faker->randomElement($tkpMateri);

            if ($i === 1) { 
                $text = 'Anda ditugaskan di bagian loket pelayanan masyarakat. Saat jam pelayanan hampir tutup dan Anda sedang merekap laporan, datang seorang lansia kebingungan membawa berkas yang salah. Sikap Anda...';
                $explanation = 'Materi: Pelayanan Publik. Poin 5 diberikan pada tindakan yang melayani dengan tulus, solutif, dan proaktif tanpa mengabaikan tugas lain terlalu lama.';
                $ops = [
                    'a' => ['text' => 'Menyuruh lansia tersebut pulang dan kembali besok pagi.', 'score' => 1],
                    'b' => ['text' => 'Menjelaskan kesalahannya, lalu memintanya segera melengkapi.', 'score' => 3],
                    'c' => ['text' => 'Menunda rekap laporan sejenak, melayani dengan ramah, dan membantunya mengurus persyaratan hari itu juga.', 'score' => 5], // Poin 5
                    'd' => ['text' => 'Meminta rekan kerja lain untuk melayani lansia tersebut.', 'score' => 4],
                    'e' => ['text' => 'Menerima berkasnya apa adanya untuk menyenangkan hatinya.', 'score' => 2],
                ];
            } else {
                $text = "(Soal TKP - {$subCat->name}) " . $faker->paragraph(2) . " Sebagai pegawai baru di instansi tersebut, sikap Anda adalah...";
                $explanation = "Pembahasan TKP: Pada aspek ini, penilaian tertinggi ditekankan pada kolaborasi dan inisiatif positif.";
                
                // TKP Poin 1 sampai 5 diacak
                $scores = [1, 2, 3, 4, 5];
                shuffle($scores);
                $ops = [
                    'a' => ['text' => $faker->sentence(6), 'score' => $scores[0]],
                    'b' => ['text' => $faker->sentence(5), 'score' => $scores[1]],
                    'c' => ['text' => $faker->sentence(7), 'score' => $scores[2]],
                    'd' => ['text' => $faker->sentence(6), 'score' => $scores[3]],
                    'e' => ['text' => $faker->sentence(8), 'score' => $scores[4]],
                ];
            }

            SkdQuestion::create([
                'skd_tryout_id'                => $tryout->id,
                'skd_question_sub_category_id' => $subCat->id,
                'main_category'                => 'tkp',
                'question_text'                => $text,
                'option_a'                     => $ops['a']['text'], 'score_a' => $ops['a']['score'],
                'option_b'                     => $ops['b']['text'], 'score_b' => $ops['b']['score'],
                'option_c'                     => $ops['c']['text'], 'score_c' => $ops['c']['score'],
                'option_d'                     => $ops['d']['text'], 'score_d' => $ops['d']['score'],
                'option_e'                     => $ops['e']['text'], 'score_e' => $ops['e']['score'],
                'explanation'                  => $explanation,
            ]);
        }
    }
}