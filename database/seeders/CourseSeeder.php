<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseSection;
use App\Models\CourseLesson;
use App\Models\CourseEnrollment;
use App\Models\CourseReview;
use App\Models\User;
use Faker\Factory as Faker;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        // Bersihkan data lama agar tidak menumpuk
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        CourseReview::truncate();
        CourseEnrollment::truncate();
        CourseLesson::truncate();
        CourseSection::truncate();
        Course::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $faker = Faker::create('id_ID');

        // 1. Author/Instructor
        $author = User::firstOrCreate(
            ['email' => 'admin@amania.id'],
            [
                'name' => 'Admin Amania',
                'password' => bcrypt('password'),
                'role' => 'superadmin' 
            ]
        );

        // 2. Buyers/Students
        $students = [];
        for ($i = 1; $i <= 5; $i++) {
            $students[] = User::firstOrCreate(
                ['email' => "siswa{$i}@gmail.com"],
                [
                    'name' => $faker->name,
                    'password' => bcrypt('password'),
                    'role' => 'user'
                ]
            );
        }

        // 3. Categories
        $categories = [
            ['name' => 'Teknologi & Programming', 'slug' => 'teknologi-programming'],
            ['name' => 'Bisnis & Marketing', 'slug' => 'bisnis-marketing'],
            ['name' => 'Pengembangan Diri', 'slug' => 'pengembangan-diri'],
        ];

        $categoryIds = [];
        foreach ($categories as $cat) {
            $category = CourseCategory::firstOrCreate(['slug' => $cat['slug']], $cat);
            $categoryIds[] = $category->id;
        }

        // 4. Courses Data with Real Indonesian Content
        $coursesData = [
            [
                'title' => 'Mastering React.js & Next.js dari Nol',
                'description' => 'Pelajari React.js dan Next.js dari dasar hingga mahir. Kursus ini membahas pembuatan komponen, state management, hingga deployment proyek ke Vercel dengan studi kasus nyata.',
                'price' => 199000,
                'level' => 'Beginner',
                'category_index' => 0,
                'sections' => [
                    [
                        'title' => 'Bab 1: Pengenalan React.js',
                        'lessons' => [
                            ['title' => 'Apa itu React.js dan Mengapa Menggunakannya?', 'type' => 'video', 'duration' => 10, 'is_preview' => true],
                            ['title' => 'Persiapan Environment & Instalasi Node.js', 'type' => 'video', 'duration' => 15, 'is_preview' => false],
                            ['title' => 'Rangkuman Instalasi & Cheatsheet', 'type' => 'text', 'duration' => 0, 'is_preview' => false],
                        ]
                    ],
                    [
                        'title' => 'Bab 2: Komponen dan State',
                        'lessons' => [
                            ['title' => 'Membuat Komponen Pertama Anda', 'type' => 'video', 'duration' => 20, 'is_preview' => false],
                            ['title' => 'Memahami State dan Props dengan Mudah', 'type' => 'video', 'duration' => 25, 'is_preview' => false],
                            ['title' => 'Source Code Latihan Bab 2', 'type' => 'file', 'duration' => 0, 'is_preview' => false],
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Digital Marketing untuk UMKM',
                'description' => 'Strategi ampuh memasarkan produk UMKM Anda menggunakan Facebook Ads, Instagram Ads, dan optimasi TikTok organik tanpa harus bakar uang. Cocok untuk pemilik bisnis online.',
                'price' => 149000,
                'level' => 'Intermediate',
                'category_index' => 1,
                'sections' => [
                    [
                        'title' => 'Bab 1: Fondasi Digital Marketing',
                        'lessons' => [
                            ['title' => 'Mindset Benar Jualan Online', 'type' => 'video', 'duration' => 12, 'is_preview' => true],
                            ['title' => 'Cara Meriset Target Market yang Tepat', 'type' => 'text', 'duration' => 0, 'is_preview' => false],
                        ]
                    ],
                    [
                        'title' => 'Bab 2: Eksekusi Iklan (Ads)',
                        'lessons' => [
                            ['title' => 'Panduan Setup Facebook Business Manager', 'type' => 'video', 'duration' => 18, 'is_preview' => false],
                            ['title' => 'Checklist Persiapan Iklan Pertama', 'type' => 'file', 'duration' => 0, 'is_preview' => false],
                        ]
                    ]
                ]
            ],
            [
                'title' => 'Public Speaking 101',
                'description' => 'Atasi rasa gugup dan bicaralah dengan percaya diri di depan umum. Kursus ini dirancang khusus untuk mahasiswa yang mau presentasi skripsi atau profesional untuk pitching klien.',
                'price' => 0,
                'level' => 'Beginner',
                'category_index' => 2,
                'sections' => [
                    [
                        'title' => 'Bab 1: Dasar Berbicara di Depan Umum',
                        'lessons' => [
                            ['title' => 'Cara Mengatasi Rasa Gugup (Nervous) Ekstrem', 'type' => 'video', 'duration' => 8, 'is_preview' => true],
                            ['title' => 'Latihan Teknik Pernapasan Perut', 'type' => 'text', 'duration' => 0, 'is_preview' => false],
                        ]
                    ],
                    [
                        'title' => 'Bab 2: Struktur Presentasi',
                        'lessons' => [
                            ['title' => 'Formula Pembukaan yang Memukau Audiens', 'type' => 'video', 'duration' => 14, 'is_preview' => false],
                        ]
                    ]
                ]
            ]
        ];

        // Daftar ulasan realistis
        $realReviews = [
            'Materi dijelaskan dengan sangat terstruktur dan mudah dipahami. Terima kasih!',
            'Wah ini sih daging semua isinya. Nyesel ga beli dari dulu.',
            'Sangat cocok buat pemula seperti saya. Penjelasan tutornya sabar dan jelas.',
            'Kualitas videonya jernih, suaranya juga jelas. Materinya sangat up to date.',
            'Luar biasa! Setelah ikut kursus ini, saya langsung bisa praktek dan hasilnya kelihatan.',
            'Harganya sangat terjangkau dibandingkan dengan value yang diberikan. Sangat direkomendasikan!',
            'Banyak insight baru yang saya dapatkan. Terima kasih Amania atas kursus berkualitas ini.',
            'Course terbaik yang pernah saya beli sejauh ini. Langsung to the point dan gak bertele-tele.'
        ];

        foreach ($coursesData as $cData) {
            $course = Course::create([
                'user_id' => $author->id,
                'course_category_id' => $categoryIds[$cData['category_index']],
                'title' => $cData['title'],
                'slug' => Str::slug($cData['title'] . '-' . Str::random(5)),
                'description' => '<p>' . $cData['description'] . '</p>',
                'price' => $cData['price'],
                'level' => $cData['level'],
                'is_published' => true,
            ]);

            // Add Sections and Lessons
            foreach ($cData['sections'] as $sIdx => $sectionData) {
                $section = CourseSection::create([
                    'course_id' => $course->id,
                    'title' => $sectionData['title'],
                    'order' => $sIdx + 1
                ]);

                foreach ($sectionData['lessons'] as $lIdx => $lessonData) {
                    $type = $lessonData['type'];
                    
                    // Generate content based on type using Indonesian text
                    $textContent = null;
                    if ($type === 'text') {
                        $textContent = '<h3>' . $lessonData['title'] . '</h3><p>Selamat datang di materi <strong>' . $lessonData['title'] . '</strong>. Di dalam sesi kali ini, kita akan membahas berbagai konsep penting, strategi jitu, dan tips yang bisa langsung Anda praktekkan.</p><ul><li>Pastikan Anda memahami fundamentalnya sebelum lanjut ke bab berikutnya.</li><li>Catat poin-poin yang menurut Anda penting.</li><li>Jangan ragu untuk berdiskusi di grup komunitas jika ada hal yang kurang dipahami.</li></ul><p>Semangat terus belajarnya! Konsistensi adalah kunci utama keberhasilan.</p>';
                    }

                    CourseLesson::create([
                        'course_section_id' => $section->id,
                        'title' => $lessonData['title'],
                        'type' => $type,
                        'youtube_url' => $type === 'video' ? 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' : null,
                        'text_content' => $textContent,
                        'file_path' => $type === 'file' ? 'dummy/materi-pendukung.pdf' : null,
                        'file_name' => $type === 'file' ? 'Materi-Pendukung-' . Str::slug($lessonData['title']) . '.pdf' : null,
                        'duration_minutes' => $lessonData['duration'],
                        'is_preview' => $lessonData['is_preview'],
                        'order' => $lIdx + 1
                    ]);
                }
            }

            // Enrollments & Reviews
            $randomStudents = $faker->randomElements($students, rand(3, 5));
            foreach ($randomStudents as $student) {
                $status = $cData['price'] == 0 ? 'PAID' : $faker->randomElement(['PAID', 'UNPAID', 'PAID']);
                
                CourseEnrollment::create([
                    'reference' => 'INV-CRS-' . strtoupper(Str::random(8)),
                    'tripay_reference' => $status === 'PAID' && $cData['price'] > 0 ? 'DEV-T' . strtoupper(Str::random(10)) : null,
                    'user_id' => $student->id,
                    'course_id' => $course->id,
                    'amount' => $cData['price'],
                    'checkout_url' => $status === 'UNPAID' ? 'https://tripay.co.id/checkout/dummy' : null,
                    'status' => $status,
                ]);

                if ($status === 'PAID') {
                    CourseReview::create([
                        'course_id' => $course->id,
                        'user_id' => $student->id,
                        'rating' => $faker->numberBetween(4, 5),
                        'comment' => $faker->randomElement($realReviews),
                    ]);
                }
            }
        }
    }
}
