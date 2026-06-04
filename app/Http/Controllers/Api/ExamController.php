<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseExam;
use App\Models\ExamAttempt;
use App\Models\CourseLessonProgress;
use App\Models\CourseEnrollment;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function getExam($slug, Request $request)
    {
        $user = $request->user();
        $course = Course::where('slug', $slug)->firstOrFail();

        // Verifikasi kepemilikan kursus
        $isEnrolled = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereIn('status', ['PAID', 'success', 'SETTLED'])
            ->exists();
        
        $isCreator = $course->user_id === $user->id;
        $isSuperadmin = $user->role === 'superadmin';

        if (!$isEnrolled && !$isCreator && !$isSuperadmin) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $exam = CourseExam::with(['questions' => function($query) {
            $query->select('id', 'course_exam_id', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d');
            // Jangan select 'correct_option' agar siswa tidak bisa curang
        }])->where('course_id', $course->id)->first();

        if (!$exam) {
            return response()->json(['success' => false, 'message' => 'Ujian tidak tersedia.'], 404);
        }

        // Cek apakah sudah pernah mencoba ujian ini
        $lastAttempt = ExamAttempt::where('user_id', $user->id)
            ->where('course_exam_id', $exam->id)
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $exam,
            'last_attempt' => $lastAttempt
        ]);
    }

    public function submitExam($slug, Request $request)
    {
        $user = $request->user();
        $course = Course::where('slug', $slug)->firstOrFail();

        $isEnrolled = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereIn('status', ['PAID', 'success', 'SETTLED'])
            ->exists();
        
        $isCreator = $course->user_id === $user->id;
        $isSuperadmin = $user->role === 'superadmin';

        if (!$isEnrolled && !$isCreator && !$isSuperadmin) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $exam = CourseExam::with('questions')->where('course_id', $course->id)->first();
        if (!$exam) {
            return response()->json(['success' => false, 'message' => 'Ujian tidak ditemukan.'], 404);
        }

        // Jawaban dari frontend (format: { questionId: 'A', questionId2: 'C' })
        $answers = $request->input('answers', []);
        $totalQuestions = $exam->questions->count();
        
        if ($totalQuestions === 0) {
            return response()->json(['success' => false, 'message' => 'Ujian belum memiliki soal.'], 400);
        }

        $correctCount = 0;
        foreach ($exam->questions as $question) {
            if (isset($answers[$question->id]) && $answers[$question->id] === $question->correct_option) {
                $correctCount++;
            }
        }

        $score = (int) round(($correctCount / $totalQuestions) * 100);
        $isPassed = $score >= $exam->passing_score;

        $attempt = ExamAttempt::create([
            'user_id' => $user->id,
            'course_exam_id' => $exam->id,
            'score' => $score,
            'is_passed' => $isPassed
        ]);

        // Jika lulus, generate sertifikat JIKA progres video juga sudah 100%
        $certificateCreated = false;
        if ($isPassed) {
            $allLessonIds = $course->sections->flatMap(fn($s) => $s->lessons->pluck('id'));
            $completedCount = CourseLessonProgress::where('user_id', $user->id)
                ->whereIn('course_lesson_id', $allLessonIds)
                ->where('is_completed', true)
                ->count();
                
            if ($completedCount === $allLessonIds->count() && $allLessonIds->count() > 0) {
                \App\Models\CourseCertificate::firstOrCreate(
                    ['user_id' => $user->id, 'course_id' => $course->id],
                    [
                        'certificate_code' => 'AMN-' . strtoupper(uniqid()) . '-' . $user->id,
                        'issued_at' => now()
                    ]
                );
                $certificateCreated = true;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Ujian selesai!',
            'data' => [
                'score' => $score,
                'is_passed' => $isPassed,
                'passing_score' => $exam->passing_score,
                'certificate_created' => $certificateCreated
            ]
        ]);
    }
}
