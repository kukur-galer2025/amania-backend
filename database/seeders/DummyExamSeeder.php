<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Course;
use App\Models\CourseExam;
use App\Models\ExamQuestion;

class DummyExamSeeder extends Seeder
{
    public function run(): void
    {
        $course = Course::first();
        if (!$course) {
            $this->command->error('Tidak ada kursus ditemukan. Tidak bisa membuat ujian.');
            return;
        }

        // Hapus ujian lama jika ada
        CourseExam::where('course_id', $course->id)->delete();

        $exam = CourseExam::create([
            'course_id' => $course->id,
            'title' => 'Ujian Akhir: Pemrograman React Dasar',
            'passing_score' => 60,
            'description' => 'Uji pemahaman Anda tentang komponen dasar React, state, dan props.',
        ]);

        $questions = [
            [
                'question_text' => 'Apa itu React?',
                'option_a' => 'Sebuah framework CSS',
                'option_b' => 'Sebuah pustaka JavaScript untuk membangun antarmuka pengguna',
                'option_c' => 'Sebuah bahasa pemrograman',
                'option_d' => 'Sebuah database relasional',
                'correct_option' => 'B'
            ],
            [
                'question_text' => 'Fungsi atau Hook apa yang digunakan untuk mengelola status (state) di functional component?',
                'option_a' => 'useProps',
                'option_b' => 'useContext',
                'option_c' => 'useState',
                'option_d' => 'useEffect',
                'correct_option' => 'C'
            ],
            [
                'question_text' => 'Apa tujuan dari useEffect Hook dalam React?',
                'option_a' => 'Mengubah styling komponen',
                'option_b' => 'Menjalankan efek samping (side effects) seperti data fetching',
                'option_c' => 'Merender ulang komponen secara paksa',
                'option_d' => 'Menyimpan nilai variabel sementara',
                'correct_option' => 'B'
            ],
            [
                'question_text' => 'Bagaimana cara meneruskan data dari komponen induk (parent) ke komponen anak (child)?',
                'option_a' => 'Melalui state',
                'option_b' => 'Melalui hooks',
                'option_c' => 'Melalui props',
                'option_d' => 'Tidak bisa meneruskan data',
                'correct_option' => 'C'
            ],
            [
                'question_text' => 'Apa keuntungan utama dari menggunakan Virtual DOM di React?',
                'option_a' => 'Membuat kode lebih panjang',
                'option_b' => 'Meningkatkan performa karena hanya memperbarui bagian yang berubah di layar',
                'option_c' => 'Menghapus kebutuhan akan HTML',
                'option_d' => 'Mengurangi penggunaan RAM browser hingga 0%',
                'correct_option' => 'B'
            ]
        ];

        foreach ($questions as $q) {
            ExamQuestion::create(array_merge($q, ['course_exam_id' => $exam->id]));
        }

        $this->command->info("Berhasil membuat ujian dummy dengan 5 pertanyaan untuk kursus: {$course->title}");
    }
}
