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
        // 1. Buat User Admin (Jika belum ada) untuk Author
        $author = User::firstOrCreate(
            ['email' => 'admin@amania.id'],
            [
                'name' => 'Tim Redaksi Amania',
                'password' => bcrypt('password123'),
                'role' => 'superadmin',
            ]
        );

        // 2. Buat Kategori Artikel
        $categories = [
            'Info CPNS & Kedinasan',
            'Tips Lulus Ujian',
            'Motivasi Belajar'
        ];

        $categoryMap = [];
        foreach ($categories as $catName) {
            $category = ArticleCategory::firstOrCreate([
                'name' => $catName,
                'slug' => Str::slug($catName),
            ]);
            $categoryMap[$catName] = $category->id;
        }

        // 3. Data 3 Artikel REAL & PROFESIONAL
        $articles = [
            [
                'title' => 'Panduan Lengkap Persiapan CPNS 2026: Syarat, Alur, dan Strategi Lulus',
                'article_category_id' => $categoryMap['Info CPNS & Kedinasan'],
                'image' => 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?q=80&w=800&auto=format&fit=crop',
                'read_time' => 5,
                'is_published' => true,
                'tags' => ['CPNS 2026', 'Syarat Pendaftaran', 'PNS', 'KemenpanRB'],
                'content' => '
                    <h2>Pendaftaran CPNS 2026 Akan Segera Dibuka</h2>
                    <p>Pemerintah melalui Kementerian Pendayagunaan Aparatur Negara dan Reformasi Birokrasi (KemenPAN-RB) terus melakukan evaluasi kebutuhan Aparatur Sipil Negara (ASN). Diperkirakan pada tahun 2026, rekrutmen CPNS akan kembali dibuka dengan fokus pada formasi tenaga pendidikan, tenaga kesehatan, dan talenta digital.</p>
                    
                    <h3>1. Persyaratan Umum yang Wajib Disiapkan</h3>
                    <p>Berdasarkan rekrutmen tahun-tahun sebelumnya, ada beberapa dokumen krusial yang wajib Anda siapkan dari sekarang agar tidak terburu-buru saat portal SSCASN dibuka:</p>
                    <ul>
                        <li><strong>Scan KTP Asli</strong> (Maksimal 200kb, format JPEG/JPG)</li>
                        <li><strong>Pas Foto Terbaru</strong> dengan latar belakang merah</li>
                        <li><strong>Ijazah Asli dan Transkrip Nilai</strong> (Bukan Surat Keterangan Lulus / SKL)</li>
                        <li><strong>Surat Lamaran dan Surat Pernyataan</strong> (Biasanya format diunduh langsung dari instansi terkait e-meterai)</li>
                    </ul>

                    <h3>2. Alur Seleksi CPNS</h3>
                    <p>Perjalanan menjadi seorang PNS tidaklah instan. Anda harus melewati 3 tahapan utama:</p>
                    <ul>
                        <li><strong>Seleksi Administrasi:</strong> Tahap pencocokan dokumen. Kesalahan kecil (seperti salah unggah dokumen) bisa berakibat fatal.</li>
                        <li><strong>Seleksi Kompetensi Dasar (SKD):</strong> Menggunakan sistem Computer Assisted Test (CAT) BKN. Meliputi Tes Wawasan Kebangsaan (TWK), Tes Intelegensia Umum (TIU), dan Tes Karakteristik Pribadi (TKP).</li>
                        <li><strong>Seleksi Kompetensi Bidang (SKB):</strong> Ujian spesifik sesuai dengan formasi jabatan yang Anda lamar.</li>
                    </ul>

                    <p><strong>Tips Amania:</strong> Jangan menunggu jadwal resmi rilis untuk mulai belajar. Kompetitor Anda sudah belajar sejak hari ini. Mulailah rutinitas harian dengan mengerjakan soal-soal latihan SKD untuk membangun insting dan kecepatan berpikir Anda.</p>
                ',
            ],
            [
                'title' => 'Rahasia Menaklukkan Tes Karakteristik Pribadi (TKP) dengan Skor Maksimal',
                'article_category_id' => $categoryMap['Tips Lulus Ujian'],
                'image' => 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=800&auto=format&fit=crop',
                'read_time' => 4,
                'is_published' => true,
                'tags' => ['TKP', 'SKD', 'Tips Menjawab', 'Passing Grade'],
                'content' => '
                    <h2>Mengapa Banyak Peserta Gagal di TKP?</h2>
                    <p>Dalam Seleksi Kompetensi Dasar (SKD) CPNS, Tes Karakteristik Pribadi (TKP) seringkali menjadi batu sandungan yang menyebabkan banyak peserta gagal memenuhi <em>Passing Grade</em> (Ambang Batas). Mengapa demikian?</p>
                    <p>Berbeda dengan TWK dan TIU yang memiliki jawaban mutlak (Benar atau Salah), setiap opsi jawaban pada soal TKP memiliki bobot nilai dari 1 hingga 5. Banyak peserta terjebak memilih jawaban yang terlihat "paling baik", namun sebenarnya tidak sesuai dengan indikator penilaian PNS.</p>

                    <h3>Mindset yang Harus Ditanamkan Saat Menjawab TKP</h3>
                    <p>Untuk mendapatkan skor poin 5 pada setiap soal, Anda harus memposisikan diri sebagai seorang pelayan publik yang profesional. Berikut adalah beberapa prinsip utamanya:</p>
                    <ul>
                        <li><strong>Orientasi pada Pelayanan Publik:</strong> Pilih jawaban yang paling memprioritaskan kepuasan dan kemudahan masyarakat, bukan kepentingan pribadi atau kenyamanan diri sendiri.</li>
                        <li><strong>Integritas Tinggi:</strong> Jangan kompromi terhadap korupsi, kolusi, dan nepotisme. Jika ada soal tentang pelanggaran aturan, pilih tindakan yang tegas melaporkan atau menolak.</li>
                        <li><strong>Adaptif terhadap Teknologi:</strong> Jika dihadapkan pada masalah efisiensi kerja, pilihlah opsi yang memanfaatkan sistem informasi atau inovasi digital.</li>
                        <li><strong>Jejaring Kerja:</strong> Pilih jawaban yang menunjukkan Anda bisa berkolaborasi dengan baik dalam tim, bukan sekadar memaksakan ego atau bekerja sendirian secara tertutup.</li>
                    </ul>

                    <h3>Hindari Jawaban "Sok Pahlawan"</h3>
                    <p>Terkadang, pembuat soal akan memberikan opsi jawaban yang sangat idealis hingga mengorbankan prosedur operasional standar (SOP). Ingat, PNS bekerja berdasarkan aturan. Jangan memilih jawaban yang menabrak SOP meskipun niatnya baik. Carilah jalan tengah yang tetap humanis namun sesuai prosedur.</p>
                    <p>Untuk mengasah kepekaan Anda dalam mencari jawaban bernilai 5, perbanyaklah melakukan simulasi melalui fitur <strong>Tryout SKD Amania</strong> yang dilengkapi dengan pembahasan dan pembobotan skor standar BKN.</p>
                ',
            ],
            [
                'title' => 'Mengapa Tryout Sistem CAT Sangat Penting Sebelum Hari H Ujian?',
                'article_category_id' => $categoryMap['Motivasi Belajar'],
                'image' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?q=80&w=800&auto=format&fit=crop',
                'read_time' => 6,
                'is_published' => true,
                'tags' => ['Simulasi CAT', 'Tryout CPNS', 'Manajemen Waktu', 'Mental Ujian'],
                'content' => '
                    <h2>Bukan Sekadar Mengukur Kemampuan Otak</h2>
                    <p>Banyak peserta CPNS maupun Sekolah Kedinasan yang gagal bukan karena mereka tidak pintar, melainkan karena mereka <strong>panik dan kehabisan waktu</strong>. Membaca buku tebal dan menghafal rumus saja tidak cukup jika Anda tidak terbiasa dengan tekanan waktu di depan layar komputer.</p>
                    <p>Inilah mengapa mengikuti simulasi menggunakan <em>Computer Assisted Test</em> (CAT) yang otentik sangatlah krusial.</p>

                    <h3>Manfaat Utama Rutin Mengerjakan Tryout CAT:</h3>
                    
                    <h4>1. Melatih Manajemen Waktu (Time Management)</h4>
                    <p>Dalam ujian SKD asli, Anda diberi waktu 100 menit untuk menyelesaikan 110 soal. Artinya, Anda hanya memiliki waktu sekitar 54 detik per soal! Jika Anda terpaku pada satu soal hitungan TIU selama 3 menit, Anda sudah mengorbankan waktu untuk 3 soal lain yang mungkin lebih mudah. Tryout CAT melatih Anda kapan harus *skip* soal dan kapan harus mengeksekusi.</p>

                    <h4>2. Adaptasi dengan User Interface (UI) BKN</h4>
                    <p>Rasa gugup di hari H sering muncul karena peserta bingung dengan tombol-tombol di layar ujian. Dengan rutin menggunakan platform tryout yang menduplikasi tampilan ujian BKN (seperti Amania Evaluation), Anda tidak akan canggung lagi saat mengklik tombol simpan, ragu-ragu, atau berpindah antar nomor soal.</p>

                    <h4>3. Membangun Stamina Mental</h4>
                    <p>Duduk menatap layar sambil berpikir keras selama 100 menit itu melelahkan. Banyak peserta yang fokusnya menurun drastis di 30 menit terakhir (terutama saat mengerjakan TKP yang teksnya panjang-panjang). Simulasi yang rutin akan membangun daya tahan fokus Anda agar tetap prima dari menit pertama hingga detik terakhir.</p>

                    <p>Jadikan tryout sebagai sarana evaluasi mingguan Anda. Kerjakan, lihat hasil skoringnya, lalu analisis di bagian mana Anda lemah. Segera perbaiki di tryout selanjutnya bersama Amania!</p>
                ',
            ],
        ];

        // 4. Proses Insert ke Database
        foreach ($articles as $articleData) {
            $articleData['slug'] = Str::slug($articleData['title']);
            $articleData['user_id'] = $author->id;
            
            // Gunakan updateOrCreate agar kalau file ini di-run berulang kali, 
            // dia akan memperbarui isinya, bukan membuat duplikat baru.
            Article::updateOrCreate(
                ['slug' => $articleData['slug']], 
                $articleData
            );
        }

        $this->command->info('Seeder 3 Artikel Realistis berhasil dijalankan!');
    }
}