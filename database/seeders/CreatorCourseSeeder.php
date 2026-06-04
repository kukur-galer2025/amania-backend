<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\CourseCategory;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CourseLesson;
use App\Models\CourseExam;
use App\Models\ExamQuestion;
use Illuminate\Support\Str;

class CreatorCourseSeeder extends Seeder
{
    public function run()
    {
        // 1. Cari user kreator
        $creator = User::where('email', 'siswa1@gmail.com')->first();
        if (!$creator) {
            $this->command->error('User siswa1@gmail.com tidak ditemukan. Jalankan DatabaseSeeder utama dulu.');
            return;
        }

        // 2. Buat Kategori (jika belum ada)
        $category = CourseCategory::firstOrCreate(
            ['slug' => 'fullstack-development'],
            ['name' => 'Fullstack Development']
        );

        // 3. Buat Kursus Lengkap
        $courseTitle = 'Mastering Next.js & Laravel (2025 Edition)';
        $course = Course::create([
            'user_id' => $creator->id,
            'course_category_id' => $category->id,
            'title' => $courseTitle,
            'slug' => Str::slug($courseTitle) . '-' . uniqid(),
            'description' => '<p>Kursus ini dirancang khusus untuk membawa Anda dari pemula menjadi developer Fullstack yang handal. Kita akan membangun aplikasi web modern menggunakan <strong>Next.js</strong> di frontend dan <strong>Laravel</strong> di backend.</p><p>Fokus utama kita adalah membangun sistem nyata seperti e-commerce atau LMS.</p>',
            'price' => 150000,
            'level' => 'intermediate',
            'thumbnail' => null,
            'is_published' => true,
        ]);

        $this->command->info('Membangun kurikulum untuk kursus: ' . $course->title);

        // 4. Buat Sections & Lessons
        $curriculum = [
            'Bab 1: Persiapan & Konsep Dasar' => [
                ['title' => 'Apa itu Next.js dan Laravel?', 'type' => 'video', 'url' => 'https://www.youtube.com/watch?v=tjvQv41bF3k', 'duration' => 15, 'preview' => true],
                ['title' => 'Instalasi Tools yang Dibutuhkan', 'type' => 'text', 'content' => '<p>Pastikan Anda telah menginstal Node.js versi 18+ dan PHP versi 8.2+.</p>', 'duration' => 5, 'preview' => false],
                ['title' => 'Silabus & Resource Pembelajaran', 'type' => 'file', 'filename' => 'silabus-lengkap.pdf', 'duration' => 2, 'preview' => true],
            ],
            'Bab 2: Membangun API dengan Laravel' => [
                ['title' => 'Setup Database & Migrations', 'type' => 'video', 'url' => 'https://www.youtube.com/watch?v=MYyJ4PuL4pY', 'duration' => 25, 'preview' => false],
                ['title' => 'Membuat REST API & Autentikasi', 'type' => 'video', 'url' => 'https://www.youtube.com/watch?v=8bJ-H2e4YpQ', 'duration' => 30, 'preview' => false],
            ],
            'Bab 3: Frontend Modern dengan Next.js' => [
                ['title' => 'Routing dan Layout di Next.js App Router', 'type' => 'video', 'url' => 'https://www.youtube.com/watch?v=843tec5hRs4', 'duration' => 20, 'preview' => false],
                ['title' => 'Integrasi API dan State Management', 'type' => 'video', 'url' => 'https://www.youtube.com/watch?v=qtzcgQx_x-M', 'duration' => 35, 'preview' => false],
                ['title' => 'Tugas Akhir Project', 'type' => 'text', 'content' => '<p>Gunakan semua yang telah Anda pelajari untuk membangun 1 fitur CRUD penuh.</p>', 'duration' => 10, 'preview' => false],
            ]
        ];

        $sectionOrder = 1;
        foreach ($curriculum as $sectionTitle => $lessons) {
            $section = CourseSection::create([
                'course_id' => $course->id,
                'title' => $sectionTitle,
                'order' => $sectionOrder++
            ]);

            $lessonOrder = 1;
            foreach ($lessons as $l) {
                CourseLesson::create([
                    'course_section_id' => $section->id,
                    'title' => $l['title'],
                    'type' => $l['type'],
                    'youtube_url' => $l['type'] === 'video' ? $l['url'] : null,
                    'text_content' => $l['type'] === 'text' ? $l['content'] : null,
                    'file_path' => $l['type'] === 'file' ? 'dummy.pdf' : null,
                    'file_name' => $l['type'] === 'file' ? $l['filename'] : null,
                    'duration_minutes' => $l['duration'],
                    'is_preview' => $l['preview'],
                    'order' => $lessonOrder++
                ]);
            }
        }

        // 5. Buat Ujian Akhir (Final Exam)
        $this->command->info('Membuat Ujian Akhir...');
        $exam = CourseExam::create([
            'course_id' => $course->id,
            'title' => 'Ujian Akhir Kelulusan Fullstack',
            'description' => 'Ujian ini dirancang untuk menguji pemahaman komprehensif Anda tentang materi Next.js dan Laravel. Anda harus mencapai nilai 80 untuk lulus.',
            'passing_score' => 80
        ]);

        // 6. Buat Soal-soal Ujian
        $questions = [
            [
                'q' => 'Apa keuntungan utama menggunakan App Router di Next.js?',
                'a' => 'Tidak bisa membuat komponen statis',
                'b' => 'Routing berbasis file dengan dukungan Server Components (RSC) secara default',
                'c' => 'Hanya berjalan di client side',
                'd' => 'Menggantikan bahasa Javascript dengan PHP',
                'ans' => 'B'
            ],
            [
                'q' => 'Perintah Artisan mana yang digunakan untuk membuat Controller sekaligus Model dan Migration di Laravel?',
                'a' => 'php artisan make:controller Product --api',
                'b' => 'php artisan create:all Product',
                'c' => 'php artisan make:model Product -m -c',
                'd' => 'php artisan generate:mvc Product',
                'ans' => 'C'
            ],
            [
                'q' => 'Fungsi dari Eloquent ORM di Laravel adalah?',
                'a' => 'Mengatur styling CSS dan Tailwind',
                'b' => 'Mengkompilasi aset frontend',
                'c' => 'Sistem routing untuk REST API',
                'd' => 'Berinteraksi dengan database menggunakan model berorientasi objek',
                'ans' => 'D'
            ],
            [
                'q' => 'Di Next.js, jika kita ingin mengambil data (fetch) di sisi client dan memantau state-nya, hook React apa yang umumnya digunakan?',
                'a' => 'useEffect dan useState',
                'b' => 'useServerData',
                'c' => 'useFetchData',
                'd' => 'useDatabase',
                'ans' => 'A'
            ],
            [
                'q' => 'Status HTTP (HTTP Status Code) manakah yang paling tepat untuk respon "Akses Ditolak (Forbidden)"?',
                'a' => '200',
                'b' => '401',
                'c' => '403',
                'd' => '404',
                'ans' => 'C'
            ]
        ];

        foreach ($questions as $q) {
            ExamQuestion::create([
                'course_exam_id' => $exam->id,
                'question_text' => $q['q'],
                'option_a' => $q['a'],
                'option_b' => $q['b'],
                'option_c' => $q['c'],
                'option_d' => $q['d'],
                'correct_option' => $q['ans']
            ]);
        }

        $this->command->info('Seeding kursus kreator berhasil dilakukan! Silakan periksa hasilnya di menu Kelola Kursus.');
    }
}
