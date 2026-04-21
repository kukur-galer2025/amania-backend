<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
        // 1. Siapkan User (Author)
        // Kita ambil user pertama yang ada di database. Jika kosong, kita buat akun dummy.
        $author = User::first() ?? User::create([
            'name' => 'Amania Editor',
            'email' => 'editor@amania.id',
            'password' => bcrypt('password'),
        ]);

        // 2. Siapkan Kategori Artikel
        $categoryTech = ArticleCategory::firstOrCreate(
            ['name' => 'Teknologi'],
            ['slug' => 'teknologi']
        );

        $categoryCareer = ArticleCategory::firstOrCreate(
            ['name' => 'Pengembangan Karier'],
            ['slug' => 'pengembangan-karier']
        );

        // =========================================================================
        // ARTIKEL 1: FOKUS TEKNOLOGI & KARIER IT
        // =========================================================================
        $title1 = 'Panduan Memulai Karier sebagai Full-Stack Developer di Era AI';
        
        $content1 = '
        <h2>Mengapa Full-Stack Developer Masih Sangat Relevan?</h2>
        <p>Di era di mana kecerdasan buatan (AI) seperti ChatGPT atau GitHub Copilot dapat menulis baris kode dalam hitungan detik, banyak yang bertanya-tanya apakah peran <strong>Full-Stack Developer</strong> akan segera usang. Jawabannya adalah: Tidak. Sebaliknya, peran ini justru berevolusi menjadi jauh lebih krusial.</p>
        <p>AI adalah alat (<em>tools</em>) yang luar biasa untuk mempercepat proses penulisan kode atau menemukan <em>bug</em>. Namun, AI belum mampu merancang arsitektur sistem secara menyeluruh, memahami konteks bisnis perusahaan, atau membuat keputusan strategis mengenai keamanan dan skalabilitas server yang kompleks.</p>
        <h3>Fokus pada Pemecahan Masalah, Bukan Sekadar Sintaks</h3>
        <p>Sebagai seorang Full-Stack Developer masa kini, nilai jual utama Anda bukan lagi seberapa cepat Anda mengetik fungsi CRUD di Laravel atau merancang komponen di React. Nilai Anda terletak pada kemampuan memecahkan masalah (<em>problem-solving</em>). Anda dituntut untuk memahami siklus penuh pengembangan perangkat lunak (SDLC).</p>
        <ul>
            <li><strong>Kuasai Fundamental:</strong> Pahami struktur data, algoritma, dan pola desain (<em>design patterns</em>). Ini adalah fondasi universal yang tidak akan pernah kedaluwarsa.</li>
            <li><strong>Jadikan AI Asisten Pribadi:</strong> Gunakan kecerdasan buatan untuk mengotomatiskan tugas-tugas repetitif seperti menulis <em>boilerplate</em> kode, sehingga Anda bisa fokus pada logika bisnis.</li>
            <li><strong>Fokus pada Arsitektur:</strong> Perluas wawasan Anda tentang <em>Microservices</em>, <em>Serverless</em>, dan infrastruktur Cloud (AWS, GCP, Azure).</li>
        </ul>
        <p>Kesimpulannya, AI tidak akan menggantikan programmer. Tetapi programmer yang mampu memanfaatkan AI, akan menggantikan mereka yang menolak beradaptasi. Teruslah belajar dan bangun portofolio Anda.</p>
        ';

        Article::firstOrCreate(
            ['slug' => Str::slug($title1)],
            [
                'title' => $title1,
                'article_category_id' => $categoryTech->id,
                'image' => null, // Bisa diisi nama file gambar default jika ada
                'content' => trim($content1),
                'read_time' => 5,
                'is_published' => true,
                'user_id' => $author->id,
                'tags' => ['Web Development', 'Karier IT', 'Artificial Intelligence', 'Full-Stack'],
            ]
        );

        // =========================================================================
        // ARTIKEL 2: FOKUS MAHASISWA & PERSONAL BRANDING
        // =========================================================================
        $title2 = 'Pentingnya Personal Branding bagi Mahasiswa IT dan Cara Membangunnya';

        $content2 = '
        <h2>Bukan Sekadar Baris Kode, Ini Tentang Reputasi</h2>
        <p>Sebagai mahasiswa program studi Informatika atau Ilmu Komputer, seringkali fokus utama kita hanya tertuju pada satu hal: <strong>bagaimana membuat aplikasi yang berfungsi tanpa error</strong>. Meskipun keahlian teknis (<em>hard skill</em>) mutlak diperlukan, ada satu aspek penentu yang sering diabaikan: <strong>Personal Branding digital</strong>.</p>
        <p>Di pasar kerja teknologi yang semakin kompetitif, selembar ijazah sarjana saja tidak lagi cukup untuk membuat Anda menonjol di mata rekruter perusahaan teknologi. Anda bersaing dengan ribuan lulusan IT lainnya setiap tahun. Di sinilah personal branding berperan. Ini adalah cara Anda menceritakan siapa Anda, keahlian apa yang Anda miliki, dan nilai tambah apa yang bisa Anda berikan kepada perusahaan.</p>
        <h3>Langkah Praktis Membangun Portofolio Digital</h3>
        <p>Membangun personal branding di dunia IT tidak berarti Anda harus menjadi seorang <em>influencer</em> media sosial. Fokuslah pada karya nyata dan rekam jejak digital (<em>digital footprint</em>) Anda.</p>
        <ol>
            <li><strong>Optimalkan Repositori GitHub:</strong> Jangan biarkan repositori GitHub Anda kosong. Unggah tugas akhir, proyek komunitas, atau hasil eksplorasi <em>coding</em> Anda. Wajib hukumnya untuk menulis <code>README.md</code> yang rapi, menjelaskan fitur aplikasi dan teknologi yang digunakan.</li>
            <li><strong>Mulai Menulis Blog Teknis:</strong> Menulis tutorial sederhana tentang <em>bug</em> yang baru saja Anda pecahkan atau <em>review</em> <em>framework</em> baru (seperti Laravel 11) menunjukkan bahwa Anda memiliki pemahaman konsep yang matang dan kemampuan komunikasi yang baik.</li>
            <li><strong>Aktif Bekerjejaring di LinkedIn:</strong> Buat profil LinkedIn yang profesional. Cantumkan tumpukan teknologi (<em>tech stack</em>) Anda, sertifikat online yang Anda peroleh, dan jangan ragu untuk terhubung dengan para praktisi IT senior.</li>
        </ol>
        <p>Ingat, reputasi digital tidak bisa dibangun dalam waktu semalam. Mulailah mendokumentasikan perjalanan belajar Anda hari ini, dan biarkan karya-karya Anda yang berbicara mewakili kualitas Anda.</p>
        ';

        Article::firstOrCreate(
            ['slug' => Str::slug($title2)],
            [
                'title' => $title2,
                'article_category_id' => $categoryCareer->id,
                'image' => null,
                'content' => trim($content2),
                'read_time' => 4,
                'is_published' => true,
                'user_id' => $author->id,
                'tags' => ['Personal Branding', 'Mahasiswa IT', 'Portofolio', 'Tips Karier'],
            ]
        );
    }
}