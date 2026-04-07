<?php

namespace Database\Seeders;

use App\Models\Article;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            // cat 2: Edukasi & Tutorial
            ['title' => 'Cara Install Laravel 11 di Windows', 'cat' => 2, 'tags' => ['laravel', 'php', 'tutorial']],
            ['title' => 'Mengenal Widget Dasar di Flutter', 'cat' => 2, 'tags' => ['flutter', 'dart', 'ui']],
            
            // cat 3: Keamanan Digital
            ['title' => 'Apa itu SQL Injection dan Cara Mencegahnya', 'cat' => 3, 'tags' => ['security', 'database', 'hacking']],
            
            // cat 5: Karir & Produktivitas
            ['title' => 'Panduan Sertifikasi Google Project Management', 'cat' => 5, 'tags' => ['pm', 'google', 'career']],
            
            // cat 2: Edukasi & Tutorial
            ['title' => 'Membuat API Sederhana dengan Python FastAPI', 'cat' => 2, 'tags' => ['python', 'api', 'backend']],
            ['title' => 'Dasar-dasar Routing pada Cisco Packet Tracer', 'cat' => 2, 'tags' => ['cisco', 'network', 'routing']],
            
            // cat 1: Teknologi
            ['title' => 'Memahami Konsep MVC di Pengembangan Aplikasi', 'cat' => 1, 'tags' => ['konsep', 'mvc', 'teknologi']],
            
            // cat 5: Karir & Produktivitas
            ['title' => 'Tips Lolos Review Aplikasi di Play Store', 'cat' => 5, 'tags' => ['android', 'playstore', 'publish']],
            
            // cat 3: Keamanan Digital
            ['title' => 'Mengenal Man in the Middle (MitM) Attack', 'cat' => 3, 'tags' => ['security', 'network', 'mitm']],
            
            // cat 4: Manajemen & Bisnis
            ['title' => 'Perbedaan Scrum dan Kanban dalam Tim IT', 'cat' => 4, 'tags' => ['agile', 'scrum', 'kanban']],
        ];

        foreach ($articles as $index => $article) {
            Article::create([
                'title' => $article['title'],
                'slug' => Str::slug($article['title']),
                'article_category_id' => $article['cat'],
                'user_id' => 1, // Asumsi 1 adalah akun Superadmin / Redaksi
                // Membuat variasi gambar dummy agar lebih bagus saat di-render
                'image' => 'articles/sample-article-' . ($index % 3 + 1) . '.jpg', 
                'content' => '<p>Ini adalah konten dummy untuk artikel <strong>' . $article['title'] . '</strong>. Di era digital saat ini, memahami konsep dan fundamental adalah kunci utama. Artikel ini membahas secara mendalam mengenai penerapan teknologi tersebut di dunia nyata beserta dampaknya terhadap efisiensi kerja tim.</p><ul><li>Poin penting pertama.</li><li>Poin penting kedua.</li></ul><p>Semoga panduan singkat ini dapat membantu perjalanan karir dan proses belajar Anda di ekosistem Amania.</p>',
                'read_time' => rand(3, 8),
                'is_published' => 1, // Angka 1 karena tipe datanya tinyInteger di database
                'tags' => $article['tags']
            ]);
        }
    }
}