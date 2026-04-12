<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\User;
use Illuminate\Support\Str;

class ArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Pastikan ada minimal 1 user (Superadmin/Author) untuk user_id
        $author = User::firstOrCreate(
            ['email' => 'admin@amania.id'],
            [
                'name' => 'Admin Amania',
                'password' => bcrypt('password123'),
                'role' => 'superadmin', // Sesuaikan dengan kolom role di tabel User kamu
            ]
        );

        // 2. Buat Kategori Artikel Asli
        $categories = [
            'Info CPNS & Kedinasan',
            'Teknologi & Programming',
            'Pengembangan Karir',
            'Tips & Trik Belajar'
        ];

        $categoryMap = [];
        foreach ($categories as $catName) {
            $category = ArticleCategory::firstOrCreate([
                'name' => $catName,
                'slug' => Str::slug($catName),
            ]);
            $categoryMap[$catName] = $category->id;
        }

        // 3. Data Artikel Asli (Real Content)
        $articles = [
            [
                'title' => 'Jadwal dan Syarat Pendaftaran CPNS 2026: Persiapkan Dirimu Dari Sekarang!',
                'article_category_id' => $categoryMap['Info CPNS & Kedinasan'],
                'image' => 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?q=80&w=800&auto=format&fit=crop',
                'read_time' => 5,
                'is_published' => true,
                'tags' => ['CPNS 2026', 'SKD', 'Info Terbaru', 'Kedinasan'],
                'content' => '
                    <h2>Pendaftaran CPNS 2026 Segera Dibuka</h2>
                    <p>Pemerintah melalui Kementerian Pendayagunaan Aparatur Negara dan Reformasi Birokrasi (KemenPAN-RB) kembali memberi sinyal kuat akan dibukanya seleksi CPNS tahun 2026. Fokus formasi tahun ini diprediksi masih berpusat pada tenaga pendidikan, tenaga kesehatan, dan talenta digital.</p>
                    <p>Bagi Anda yang bermimpi menjadi Abdi Negara, persiapan yang matang adalah kunci utama. Ujian berbasis <strong>Computer Assisted Test (CAT)</strong> membutuhkan kecepatan dan ketepatan.</p>
                    <h3>Tahapan Seleksi yang Harus Dilalui:</h3>
                    <ul>
                        <li><strong>Seleksi Administrasi:</strong> Pastikan dokumen seperti Ijazah, Transkrip Nilai, dan e-KTP valid.</li>
                        <li><strong>Seleksi Kompetensi Dasar (SKD):</strong> Terdiri dari Tes Wawasan Kebangsaan (TWK), Tes Intelegensia Umum (TIU), dan Tes Karakteristik Pribadi (TKP).</li>
                        <li><strong>Seleksi Kompetensi Bidang (SKB):</strong> Tes spesifik sesuai formasi yang dilamar.</li>
                    </ul>
                    <p>Jangan tunggu jadwal resmi keluar baru belajar! Gunakan layanan <strong>Tryout SKD Amania</strong> untuk mensimulasikan ujian CAT BKN lengkap dengan soal HOTS terbaru.</p>
                ',
            ],
            [
                'title' => 'Mengenal VILT Stack: Kombinasi Ampuh Vue, Inertia, Laravel, dan Tailwind',
                'article_category_id' => $categoryMap['Teknologi & Programming'],
                'image' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?q=80&w=800&auto=format&fit=crop',
                'read_time' => 7,
                'is_published' => true,
                'tags' => ['Laravel', 'Vue JS', 'Web Development', 'Programming'],
                'content' => '
                    <h2>Apa itu VILT Stack?</h2>
                    <p>Dalam dunia Web Development modern, membangun aplikasi <em>Single Page Application (SPA)</em> seringkali dianggap rumit karena harus memisahkan Backend (API) dan Frontend secara penuh. Namun, dengan hadirnya <strong>VILT Stack</strong> (Vue.js, Inertia.js, Laravel, Tailwind CSS), paradigma itu berubah drastis.</p>
                    <p>VILT Stack memungkinkan developer membangun aplikasi berskala besar layaknya SPA, namun dengan cara kerja routing dan controller klasik ala Laravel. Anda tidak perlu pusing memikirkan manajemen state yang kompleks seperti Redux atau Vuex.</p>
                    <h3>Kelebihan Menggunakan VILT Stack:</h3>
                    <ul>
                        <li><strong>Pengembangan Lebih Cepat:</strong> Tidak perlu membuat API terpisah. Cukup kembalikan data melalui <code>Inertia::render()</code> dari controller Laravel Anda.</li>
                        <li><strong>Performa Tinggi:</strong> Navigasi antar halaman dilakukan tanpa reload penuh berkat Inertia.js.</li>
                        <li><strong>Desain Modern:</strong> Tailwind CSS memberikan kebebasan styling langsung dari class HTML tanpa perlu menulis file CSS terpisah.</li>
                    </ul>
                    <p>Bagi mahasiswa Informatika atau pemula yang ingin terjun ke dunia Full-stack Development, menguasai VILT Stack (atau TALL stack dengan Livewire) adalah nilai jual yang sangat tinggi di industri saat ini.</p>
                ',
            ],
            [
                'title' => 'Strategi Menaklukkan Soal TKP CPNS dengan Skor Maksimal',
                'article_category_id' => $categoryMap['Tips & Trik Belajar'],
                'image' => 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=800&auto=format&fit=crop',
                'read_time' => 4,
                'is_published' => true,
                'tags' => ['SKD CPNS', 'Tips Lulus', 'TKP', 'PNS'],
                'content' => '
                    <h2>Mengapa TKP Sering Menjadi Momok?</h2>
                    <p>Tes Karakteristik Pribadi (TKP) adalah salah satu bagian dari SKD CPNS yang tidak memiliki jawaban benar atau salah mutlak. Setiap opsi jawaban memiliki bobot nilai dari 1 hingga 5. Banyak peserta gagal melewati <em>Passing Grade</em> TKP karena salah dalam memilih sudut pandang saat menjawab.</p>
                    <h3>Tips Jitu Menjawab Soal TKP:</h3>
                    <ul>
                        <li><strong>Posisikan Diri Sebagai Pelayan Publik:</strong> Ingatlah bahwa PNS adalah pelayan masyarakat. Pilihlah jawaban yang paling berorientasi pada kepuasan publik, bukan kepentingan pribadi.</li>
                        <li><strong>Hindari Jawaban Ekstrem:</strong> Terkadang jawaban yang terlalu "sempurna" atau mengorbankan segalanya demi pekerjaan bukanlah jawaban bernilai 5. Cari opsi yang seimbang, profesional, dan realistis.</li>
                        <li><strong>Perhatikan Indikator Soal:</strong> Setiap soal TKP memiliki indikator spesifik (misal: Jejaring Kerja, Profesionalisme, TIK). Jika soal membahas tentang teknologi, pilihlah jawaban yang memanfaatkan teknologi untuk menyelesaikan masalah.</li>
                    </ul>
                    <p>Cara terbaik mengasah insting TKP adalah dengan memperbanyak latihan soal. Anda bisa mengakses ribuan soal TKP ter-update di platform Tryout Amania sekarang juga!</p>
                ',
            ],
            [
                'title' => 'Pentingnya Sertifikasi Profesional di Era Digital, Apakah Gelar Sarjana Saja Cukup?',
                'article_category_id' => $categoryMap['Pengembangan Karir'],
                'image' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?q=80&w=800&auto=format&fit=crop',
                'read_time' => 6,
                'is_published' => true,
                'tags' => ['Karir', 'Sertifikasi', 'Self Development', 'Dunia Kerja'],
                'content' => '
                    <h2>Gelar Sarjana Saja Tidak Cukup</h2>
                    <p>Di era persaingan kerja yang semakin ketat, memiliki ijazah S1 tidak lagi menjadi satu-satunya jaminan untuk diterima di perusahaan impian. HRD dan Recruiter masa kini lebih mencari kandidat yang memiliki keterampilan praktis yang dapat dibuktikan.</p>
                    <p>Di sinilah <strong>Sertifikasi Profesional</strong> mengambil peran penting. Sertifikasi adalah bukti valid yang dikeluarkan oleh lembaga otoritatif bahwa Anda memiliki kompetensi khusus di bidang tertentu.</p>
                    <h3>Manfaat Memiliki Sertifikasi:</h3>
                    <ul>
                        <li><strong>Validasi Skill:</strong> Sertifikasi dari Google, Cisco, atau Microsoft jauh lebih dilirik daripada sekadar mencantumkan "Menguasai Jaringan" di CV.</li>
                        <li><strong>Meningkatkan Gaji:</strong> Karyawan bersertifikasi umumnya memiliki daya tawar yang lebih tinggi saat negosiasi gaji.</li>
                        <li><strong>Update Pengetahuan:</strong> Silabus sertifikasi profesional selalu disesuaikan dengan tren industri terbaru, berbeda dengan kurikulum kampus yang kadang tertinggal.</li>
                    </ul>
                    <p>Mulailah merencanakan karir Anda. Ikuti berbagai Webinar dan Kelas Intensif di Amania untuk mempersiapkan diri menghadapi ujian sertifikasi global!</p>
                ',
            ],
        ];

        // 4. Masukkan ke Database
        foreach ($articles as $articleData) {
            $articleData['slug'] = Str::slug($articleData['title']);
            $articleData['user_id'] = $author->id;
            
            Article::firstOrCreate(
                ['slug' => $articleData['slug']], // Cek agar tidak duplikat
                $articleData
            );
        }

        $this->command->info('Seeder Artikel (Real Content) berhasil dijalankan!');
    }
}