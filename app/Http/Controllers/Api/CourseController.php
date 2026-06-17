<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLessonProgress;
use App\Models\CourseReview;
use App\Models\EProduct;
use App\Models\Event;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    // =========================================================================
    // 1. KATALOG KURSUS (PUBLIC)
    // =========================================================================
    public function index()
    {
        $courses = Course::where('is_published', true)
            ->with(['category', 'instructor'])
            ->withCount(['sections', 'reviews'])
            ->withAvg('reviews', 'rating')
            ->get()
            ->map(function ($course) {
                // Count lessons and total duration across all sections
                $course->loadMissing('sections.lessons');
                $course->lessons_count = $course->sections->sum(fn($s) => $s->lessons->count());
                $course->total_duration = $course->sections->sum(fn($s) => $s->lessons->sum('duration_minutes'));
                $course->students_count = CourseEnrollment::where('course_id', $course->id)
                    ->whereIn('status', ['PAID', 'success', 'SETTLED'])
                    ->distinct('user_id')
                    ->count('user_id');
                $course->avg_rating = round((float) $course->reviews_avg_rating, 1);
                // Unload sections.lessons to keep response clean
                unset($course->sections);
                return $course;
            });

        return response()->json(['success' => true, 'data' => $courses]);
    }

    // =========================================================================
    // 2. DETAIL KURSUS (PUBLIC, with is_enrolled check if auth)
    // =========================================================================
    public function show(Request $request, $slug)
    {
        $course = Course::where(function ($q) use ($slug) {
            $q->where('slug', $slug)->orWhere('id', $slug);
        })
            ->where('is_published', true)
            ->with(['category', 'instructor', 'sections.lessons', 'reviews.user'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->firstOrFail();

        // Count students
        $course->students_count = CourseEnrollment::where('course_id', $course->id)
            ->whereIn('status', ['PAID', 'success', 'SETTLED'])
            ->distinct('user_id')
            ->count('user_id');
        $course->avg_rating = round((float) $course->reviews_avg_rating, 1);

        // Check if current user is enrolled (works even on public route without middleware)
        $course->is_enrolled = false;
        $course->user_review = null;
        $user = \Illuminate\Support\Facades\Auth::guard('sanctum')->user();
        if ($user) {
            $course->is_enrolled = CourseEnrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->whereIn('status', ['PAID', 'success', 'SETTLED'])
                ->exists();
            $course->user_review = CourseReview::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->first();
        }

        return response()->json(['success' => true, 'data' => $course]);
    }

    // =========================================================================
    // 3. KURSUS SAYA (MEMBER) — Semua kursus yang sudah di-enroll + PAID
    // =========================================================================
    public function myCourses(Request $request)
    {
        $user = $request->user();

        $enrolledCourseIds = CourseEnrollment::where('user_id', $user->id)
            ->whereIn('status', ['PAID', 'success', 'SETTLED'])
            ->pluck('course_id')
            ->unique();

        $courses = Course::whereIn('id', $enrolledCourseIds)
            ->with(['category', 'instructor', 'sections.lessons'])
            ->get()
            ->map(function ($course) use ($user) {
                $totalLessons = $course->sections->sum(fn($s) => $s->lessons->count());
                $allLessonIds = $course->sections->flatMap(fn($s) => $s->lessons->pluck('id'));

                $completedCount = CourseLessonProgress::where('user_id', $user->id)
                    ->whereIn('course_lesson_id', $allLessonIds)
                    ->where('is_completed', true)
                    ->count();

                $course->total_lessons = $totalLessons;
                $course->completed_lessons = $completedCount;
                $course->progress_percent = $totalLessons > 0 ? round(($completedCount / $totalLessons) * 100) : 0;
                $course->total_duration = $course->sections->sum(fn($s) => $s->lessons->sum('duration_minutes'));
                $course->sections_count = $course->sections->count();

                // Include user's review for this course
                $course->user_review = CourseReview::where('user_id', $user->id)
                    ->where('course_id', $course->id)
                    ->first();

                return $course;
            });

        return response()->json(['success' => true, 'data' => $courses]);
    }

    // =========================================================================
    // 4. RIWAYAT TRANSAKSI KURSUS (MEMBER)
    // =========================================================================
    public function myTransactions(Request $request)
    {
        $transactions = CourseEnrollment::where('user_id', $request->user()->id)
            ->with(['course'])
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $transactions]);
    }

    // =========================================================================
    // 5. DATA BELAJAR (MEMBER — must be enrolled)
    // =========================================================================
    public function learnCourse(Request $request, $slug)
    {
        $user = $request->user();

        $course = Course::where('slug', $slug)
            ->with(['sections.lessons', 'instructor'])
            ->firstOrFail();

        // Check enrollment
        $isEnrolled = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereIn('status', ['PAID', 'success', 'SETTLED'])
            ->exists();
            
        $isCreator = $course->user_id === $user->id;
        $isSuperadmin = $user->role === 'superadmin';

        if (!$isEnrolled && !$isCreator && !$isSuperadmin) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum memiliki akses ke kursus ini.'
            ], 403);
        }

        // Get progress
        $allLessonIds = $course->sections->flatMap(fn($s) => $s->lessons->pluck('id'));
        $completedLessonIds = CourseLessonProgress::where('user_id', $user->id)
            ->whereIn('course_lesson_id', $allLessonIds)
            ->where('is_completed', true)
            ->pluck('course_lesson_id');

        $certificate = \App\Models\CourseCertificate::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        $hasExam = \App\Models\CourseExam::where('course_id', $course->id)
            ->whereHas('questions')
            ->exists();

        // Return flat structure matching LearnClient.tsx expectations
        return response()->json([
            'success' => true,
            'data' => [
                'title'             => $course->title,
                'slug'              => $course->slug,
                'sections'          => $course->sections,
                'instructor'        => $course->instructor,
                'completed_lessons' => $completedLessonIds,
                'certificate'       => $certificate,
                'has_exam'          => $hasExam,
            ]
        ]);
    }

    // =========================================================================
    // 6. TANDAI LESSON SELESAI (MEMBER)
    // =========================================================================
    public function markProgress(Request $request)
    {
        $request->validate([
            'course_lesson_id' => 'required|exists:course_lessons,id',
        ]);

        $user = $request->user();

        // Verify enrollment: lesson -> section -> course -> enrollment
        $lessonId = $request->course_lesson_id;
        $lesson = \App\Models\CourseLesson::with('section.course')->findOrFail($lessonId);

        $isEnrolled = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $lesson->section->course->id)
            ->whereIn('status', ['PAID', 'success', 'SETTLED'])
            ->exists();
            
        $isCreator = $lesson->section->course->user_id === $user->id;
        $isSuperadmin = $user->role === 'superadmin';

        if (!$isEnrolled && !$isCreator && !$isSuperadmin) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        CourseLessonProgress::updateOrCreate(
            ['user_id' => $user->id, 'course_lesson_id' => $lessonId],
            ['is_completed' => true]
        );

        return response()->json([
            'success' => true,
            'message' => 'Progress berhasil disimpan.'
        ]);
    }

    // =========================================================================
    // 7. TANDAI LESSON SELESAI VIA SLUG (ALIAS untuk frontend)
    // =========================================================================
    public function markProgressBySlug(Request $request, $slug)
    {
        // Frontend sends lesson_id instead of course_lesson_id
        $lessonId = $request->input('lesson_id') ?? $request->input('course_lesson_id');

        if (!$lessonId) {
            return response()->json(['success' => false, 'message' => 'lesson_id wajib diisi.'], 422);
        }

        $user = $request->user();
        $lesson = \App\Models\CourseLesson::with('section.course')->findOrFail($lessonId);

        $isEnrolled = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $lesson->section->course->id)
            ->whereIn('status', ['PAID', 'success', 'SETTLED'])
            ->exists();
            
        $isCreator = $lesson->section->course->user_id === $user->id;
        $isSuperadmin = $user->role === 'superadmin';

        if (!$isEnrolled && !$isCreator && !$isSuperadmin) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        CourseLessonProgress::updateOrCreate(
            ['user_id' => $user->id, 'course_lesson_id' => $lessonId],
            ['is_completed' => true]
        );

        $courseId = $lesson->section->course->id;
        $course = $lesson->section->course;
        
        // Check for 100% completion
        $allLessonIds = $course->sections->flatMap(fn($s) => $s->lessons->pluck('id'));
        $completedCount = CourseLessonProgress::where('user_id', $user->id)
            ->whereIn('course_lesson_id', $allLessonIds)
            ->where('is_completed', true)
            ->count();
            
        if ($completedCount === $allLessonIds->count() && $allLessonIds->count() > 0) {
            // Cek apakah kursus punya ujian
            $exam = \App\Models\CourseExam::where('course_id', $courseId)->first();
            
            // Jika tidak ada ujian, atau jika ada ujian dan sudah lulus, berikan sertifikat
            $canGetCertificate = true;
            if ($exam) {
                $passedExam = \App\Models\ExamAttempt::where('user_id', $user->id)
                    ->where('course_exam_id', $exam->id)
                    ->where('is_passed', true)
                    ->exists();
                if (!$passedExam) $canGetCertificate = false;
            }

            if ($canGetCertificate) {
                \App\Models\CourseCertificate::firstOrCreate(
                    ['user_id' => $user->id, 'course_id' => $courseId],
                    [
                        'certificate_code' => 'AMN-' . strtoupper(uniqid()) . '-' . $user->id,
                        'issued_at' => now()
                    ]
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Progress berhasil disimpan.'
        ]);
    }

    // =========================================================================
    // 7.5. DOWNLOAD SERTIFIKAT (MEMBER)
    // =========================================================================
    public function downloadCertificate(Request $request, $slug)
    {
        // Support both: auth:sanctum middleware OR ?token= query param (for <a target="_blank">)
        $user = $request->user();
        if (!$user && $request->query('token')) {
            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($request->query('token'));
            if ($token) {
                $user = $token->tokenable;
            }
        }

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }
        $course = Course::where('slug', $slug)->firstOrFail();

        $certificate = \App\Models\CourseCertificate::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if (!$certificate) {
            return response()->json(['success' => false, 'message' => 'Sertifikat belum tersedia.'], 404);
        }

        $totalMinutes = \App\Models\CourseLesson::whereHas('section', function($q) use ($course) {
            $q->where('course_id', $course->id);
        })->sum('duration_minutes');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.certificate', [
            'user' => $user,
            'course' => $course,
            'certificate' => $certificate,
            'totalMinutes' => $totalMinutes
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('Sertifikat-' . $course->slug . '.pdf');
    }

    // =========================================================================
    // 8. DOWNLOAD FILE LESSON (MEMBER)
    // =========================================================================
    public function downloadLessonFile(Request $request, $lessonId)
    {
        // Support both: auth:sanctum middleware OR ?token= query param (for <a target="_blank">)
        $user = $request->user();
        if (!$user && $request->query('token')) {
            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($request->query('token'));
            if ($token) {
                $user = $token->tokenable;
            }
        }

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $lesson = \App\Models\CourseLesson::with('section.course')->findOrFail($lessonId);

        // Check enrollment
        $isEnrolled = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $lesson->section->course->id)
            ->whereIn('status', ['PAID', 'success', 'SETTLED'])
            ->exists();

        if (!$isEnrolled) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        if ($lesson->type !== 'file' || !$lesson->file_path || !\Illuminate\Support\Facades\Storage::disk('public')->exists($lesson->file_path)) {
            return response()->json(['success' => false, 'message' => 'File tidak ditemukan.'], 404);
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->download($lesson->file_path, $lesson->file_name ?? 'download');
    }

    // =========================================================================
    // 9. SUBMIT / UPDATE REVIEW
    // =========================================================================
    public function submitReview(Request $request, $slug)
    {
        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $course = Course::where('slug', $slug)->firstOrFail();

        // Check enrollment
        $isEnrolled = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereIn('status', ['PAID', 'success', 'SETTLED'])
            ->exists();

        if (!$isEnrolled) {
            return response()->json(['success' => false, 'message' => 'Anda harus membeli kursus ini untuk memberi rating.'], 403);
        }

        $review = CourseReview::updateOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            ['rating' => $request->rating, 'comment' => $request->comment]
        );

        return response()->json([
            'success' => true,
            'message' => 'Review berhasil disimpan!',
            'data'    => $review->load('user'),
        ]);
    }

    // =========================================================================
    // 10. GET REVIEWS FOR COURSE
    // =========================================================================
    public function getReviews($slug)
    {
        $course = Course::where('slug', $slug)->firstOrFail();

        $reviews = CourseReview::where('course_id', $course->id)
            ->with('user')
            ->latest()
            ->get();

        $avgRating = $reviews->avg('rating');

        return response()->json([
            'success'    => true,
            'data'       => $reviews,
            'avg_rating' => round((float) $avgRating, 1),
            'total'      => $reviews->count(),
        ]);
    }

    // =========================================================================
    // 11. AI MENTOR
    // =========================================================================
    
    public function clearMentorChats(Request $request, $lessonId)
    {
        $user = $request->user();
        \App\Models\AiMentorChat::where('user_id', $user->id)
            ->where('lesson_id', $lessonId)
            ->delete();

        return response()->json(['success' => true, 'message' => 'Chat history cleared.']);
    }
    public function getMentorChats(Request $request, $lessonId)
    {
        $user = $request->user();
        $lesson = \App\Models\CourseLesson::with('section.course')->findOrFail($lessonId);

        // Check enrollment
        $isEnrolled = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $lesson->section->course->id)
            ->whereIn('status', ['PAID', 'success', 'SETTLED'])
            ->exists();

        $isCreator = $lesson->section->course->user_id === $user->id;
        $isSuperadmin = $user->role === 'superadmin';

        if (!$isEnrolled && !$isCreator && !$isSuperadmin) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $chats = \App\Models\AiMentorChat::where('user_id', $user->id)
            ->where('lesson_id', $lessonId)
            ->orderBy('created_at', 'asc')
            ->get(['role', 'content']);

        return response()->json(['success' => true, 'data' => $chats]);
    }

    public function askMentor(Request $request, $lessonId)
    {
        $request->validate([
            'question' => 'required|string|max:2000'
        ]);

        $user = $request->user();
        $lesson = \App\Models\CourseLesson::with('section.course')->findOrFail($lessonId);

        // Check enrollment
        $isEnrolled = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $lesson->section->course->id)
            ->whereIn('status', ['PAID', 'success', 'SETTLED'])
            ->exists();

        $isCreator = $lesson->section->course->user_id === $user->id;
        $isSuperadmin = $user->role === 'superadmin';

        if (!$isEnrolled && !$isCreator && !$isSuperadmin) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $question = $request->input('question');
        $geminiApiKey = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');

        // Simpan Chat User ke DB
        \App\Models\AiMentorChat::create([
            'user_id' => $user->id,
            'lesson_id' => $lessonId,
            'role' => 'user',
            'content' => $question
        ]);

        if (!$geminiApiKey) {
            $reply = "API Key Gemini belum diatur. Mohon hubungi admin.";
            
            \App\Models\AiMentorChat::create([
                'user_id' => $user->id,
                'lesson_id' => $lessonId,
                'role' => 'model',
                'content' => $reply
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'reply' => $reply
                ]
            ]);
        }

        // Ambil riwayat chat sebelumnya (maksimal 10 percakapan terakhir)
        $history = \App\Models\AiMentorChat::where('user_id', $user->id)
            ->where('lesson_id', $lessonId)
            ->orderBy('created_at', 'desc') // Ambil terbaru
            ->take(10)
            ->get()
            ->reverse(); // Urutkan kembali menjadi terlama ke terbaru

        $contents = [];
        foreach ($history as $chat) {
            $contents[] = [
                'role' => $chat->role,
                'parts' => [['text' => $chat->content]]
            ];
        }

        // RAG (Knowledge Retrieval) & System Prompt
        $sysPrompt = "Anda adalah 'AI Mentor' di platform e-learning Amania.\n";
        $sysPrompt .= "Tugas Anda adalah mendampingi siswa memahami materi '{$lesson->title}' pada kursus '{$lesson->section->course->title}'.\n";
        $sysPrompt .= "Gunakan metode Socratic (membimbing, tidak selalu langsung memberikan jawaban akhir).\n\n";
        
        $sysPrompt .= "=== REFERENSI MATERI ===\n";
        if (!empty($lesson->description)) {
            $sysPrompt .= "Ringkasan:\n" . strip_tags($lesson->description) . "\n\n";
        }
        if (!empty($lesson->text_content)) {
            $sysPrompt .= "Isi Materi:\n" . strip_tags($lesson->text_content) . "\n\n";
        }
        $sysPrompt .= "========================\n\n";
        
        if ($lesson->type === 'video' && !empty($lesson->youtube_url)) {
            $sysPrompt .= "Catatan Tambahan: Materi ini berbentuk video YouTube. Tautan video: " . $lesson->youtube_url . "\n";
            $sysPrompt .= "Jika siswa meminta Anda merangkum materi atau menjelaskan isi video ini, Anda HARUS merangkum berdasarkan TRANSCRIPT (Ucapan Asli dalam Video) berikut:\n\n";
            $sysPrompt .= "=== YOUTUBE TRANSCRIPT ===\n";

            if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $lesson->youtube_url, $match)) {
                $videoId = $match[1];
                try {
                    $client = new \GuzzleHttp\Client();
                    $requestFactory = new \GuzzleHttp\Psr7\HttpFactory();
                    $streamFactory = new \GuzzleHttp\Psr7\HttpFactory();
                    $fetcher = new \MrMySQL\YoutubeTranscript\TranscriptListFetcher($client, $requestFactory, $streamFactory);
                    
                    $transcriptList = $fetcher->fetch($videoId);
                    $langCodes = $transcriptList->getAvailableLanguageCodes();
                    $transcript = $transcriptList->findTranscript($langCodes);
                    $transcriptData = $transcript->fetch();
                    
                    $fullTranscript = '';
                    foreach ($transcriptData as $item) {
                        $fullTranscript .= $item['text'] . ' ';
                    }
                    
                    if (strlen($fullTranscript) > 15000) {
                        $fullTranscript = substr($fullTranscript, 0, 15000) . "... [transcript dipotong]";
                    }
                    $sysPrompt .= $fullTranscript . "\n";
                } catch (\Exception $e) {
                    $sysPrompt .= "(Transcript gagal ditarik otomatis. Silakan andalkan Ringkasan teks materi di atas untuk merangkum).\n";
                }
            } else {
                $sysPrompt .= "(Format link YouTube tidak dikenali).\n";
            }
            $sysPrompt .= "==========================\n\n";
        }

        $sysPrompt .= "INSTRUKSI QUIZ MASTER: Jika siswa meminta untuk 'diuji', ubah peran Anda menjadi 'Quiz Master'. Berikan 1 pertanyaan pilihan ganda menantang berdasarkan materi. JANGAN beritahu jawabannya di awal. Tunggu siswa menjawab, lalu berikan skor (0-100) dan koreksi edukatif.\n";
        $sysPrompt .= "FORMAT SOAL KUIS: Anda WAJIB menggunakan struktur persis seperti ini:\n";
        $sysPrompt .= "[Pertanyaan Anda]\n\n";
        $sysPrompt .= "- **A.** [Opsi A]\n";
        $sysPrompt .= "- **B.** [Opsi B]\n";
        $sysPrompt .= "- **C.** [Opsi C]\n";
        $sysPrompt .= "- **D.** [Opsi D]\n\n";

        $sysPrompt .= "Jawablah dengan bahasa Indonesia yang ramah dan profesional. Jawab secara ringkas dan tepat sasaran.\n";
        $sysPrompt .= "PENTING: Di baris paling bawah dari setiap jawaban Anda, Anda WAJIB memberikan format rekomendasi lanjutan yang diawali dengan '|||'. Gunakan persis format ini di baris terakhir:\n";
        $sysPrompt .= "Jika Anda baru saja memberikan soal kuis pilihan ganda, WAJIB tuliskan persis ini di baris terakhir: |||Jawaban A|Jawaban B|Jawaban C|Jawaban D\n";
        $sysPrompt .= "Jika percakapan biasa (bukan soal kuis), tuliskan 3 contoh pertanyaan lanjutan: |||Pertanyaan 1|Pertanyaan 2|Pertanyaan 3\n";

        $payload = [
            'system_instruction' => [
                'parts' => ['text' => $sysPrompt]
            ],
            'contents' => $contents
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:streamGenerateContent?alt=sse&key=' . $geminiApiKey;

        return response()->stream(function () use ($url, $payload, $user, $lessonId) {
            // Bypass reverse proxy buffering (Nginx, Cloudflare)
            echo ":" . str_repeat(" ", 2048) . "\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            $fullText = "";

            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$fullText) {
                $lines = explode("\n", $data);
                foreach ($lines as $line) {
                    if (strpos($line, 'data: ') === 0) {
                        $jsonStr = substr($line, 6);
                        $json = json_decode($jsonStr, true);
                        if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                            $chunkText = $json['candidates'][0]['content']['parts'][0]['text'];
                            $fullText .= $chunkText;
                            
                            echo "data: " . json_encode(['chunk' => $chunkText]) . "\n\n";
                            if (ob_get_level() > 0) ob_flush();
                            flush();
                        }
                    }
                }
                return strlen($data);
            });

            curl_exec($ch);
            curl_close($ch);

            if (!empty($fullText)) {
                \App\Models\AiMentorChat::create([
                    'user_id' => $user->id,
                    'lesson_id' => $lessonId,
                    'role' => 'model',
                    'content' => $fullText
                ]);
            }
            
            echo "data: [DONE]\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();

        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive'
        ]);
    }

    // =========================================================================
    // 12. AI COURSE ADVISOR (GLOBAL)
    // =========================================================================
    public function askCourseAdvisor(Request $request)
    {
        $request->validate([
            'messages' => 'required|array'
        ]);

        $geminiApiKey = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');
        if (!$geminiApiKey) {
            return response()->json(['error' => 'Gemini API key not configured'], 500);
        }

        // Fetch all published courses
        $courses = Course::where('is_published', true)
            ->with('category:id,name')
            ->get(['id', 'title', 'slug', 'level', 'price', 'description', 'course_category_id'])
            ->map(function($course) {
                return [
                    'type' => 'Course (Kursus Online)',
                    'title' => $course->title,
                    'slug' => '/courses/' . $course->slug,
                    'level' => $course->level,
                    'price' => $course->price == 0 ? 'Gratis' : 'Rp ' . number_format($course->price, 0, ',', '.'),
                    'category' => $course->category->name ?? 'Uncategorized',
                    'description' => substr(strip_tags($course->description), 0, 200) . '...'
                ];
            });

        // Fetch all published E-Products
        $eproducts = EProduct::where('is_published', true)
            ->with('category:id,name')
            ->get(['id', 'title', 'slug', 'price', 'description', 'e_product_category_id'])
            ->map(function($ep) {
                return [
                    'type' => 'E-Product (Buku/Template/Materi)',
                    'title' => $ep->title,
                    'slug' => '/e-products/' . $ep->slug,
                    'price' => $ep->price == 0 ? 'Gratis' : 'Rp ' . number_format($ep->price, 0, ',', '.'),
                    'category' => $ep->category->name ?? 'Uncategorized',
                    'description' => substr(strip_tags($ep->description), 0, 200) . '...'
                ];
            });

        // Fetch all active Events (upcoming webinars)
        $events = Event::where('start_time', '>=', now())
            ->get(['id', 'title', 'slug', 'basic_price', 'description', 'start_time'])
            ->map(function($event) {
                return [
                    'type' => 'Webinar / Event Live',
                    'title' => $event->title,
                    'slug' => '/events/' . $event->slug,
                    'price' => $event->basic_price == 0 ? 'Gratis' : 'Rp ' . number_format($event->basic_price, 0, ',', '.'),
                    'start_time' => $event->start_time ? $event->start_time->format('Y-m-d H:i') : null,
                    'description' => substr(strip_tags($event->description), 0, 200) . '...'
                ];
            });

        $allCatalog = array_merge($courses->toArray(), $eproducts->toArray(), $events->toArray());

        $sysPrompt = "Anda adalah 'Amania AI Course & Career Advisor', asisten virtual interaktif, ramah, dan sangat membantu di platform edukasi Amania Nusantara Professional.\n";
        $sysPrompt .= "Tugas Anda adalah merekomendasikan produk edukasi terbaik berdasarkan minat, karir, atau kebutuhan pengguna.\n\n";
        $sysPrompt .= "Berikut adalah DAFTAR SEMUA PRODUK (KURSUS, E-PRODUK, DAN WEBINAR) yang tersedia di platform Amania:\n";
        $sysPrompt .= json_encode($allCatalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
        
        $sysPrompt .= "ATURAN UTAMA:\n";
        $sysPrompt .= "1. Jika pengguna bertanya tentang rekomendasi produk (kursus, ebook, webinar), cocokkan dengan daftar di atas.\n";
        $sysPrompt .= "2. JIKA ADA yang cocok, WAJIB sertakan tautan ke produk tersebut dengan format Markdown absolut path menggunakan field slug yang tersedia (contoh: [Judul](/courses/slug)). JANGAN PERNAH membuat tautan palsu.\n";
        $sysPrompt .= "3. Jika tidak ada kursus yang benar-benar relevan dengan permintaan pengguna, katakan dengan jujur bahwa saat ini belum ada kursus spesifik tentang itu, dan rekomendasikan kursus terdekat yang mungkin berguna.\n";
        $sysPrompt .= "4. Bersikaplah seperti Konsultan Karir. Tanyakan tujuan mereka jika mereka bingung.\n";
        $sysPrompt .= "5. Gunakan bahasa Indonesia yang santai tapi profesional (gunakan kata ganti 'Anda').\n";
        $sysPrompt .= "6. PENTING: Di baris PALING BAWAH jawaban Anda, WAJIB tuliskan 3 contoh saran balasan/pertanyaan yang bisa diklik pengguna, dipisahkan oleh tanda '|||'.\n";
        $sysPrompt .= "Saran balasan INI HARUS DITULIS DARI SUDUT PANDANG PENGGUNA (seolah-olah pengguna yang mengatakannya kepada Anda).\n";
        $sysPrompt .= "Contoh format yang benar di baris terakhir:\n";
        $sysPrompt .= "|||Saya pemula, dari mana saya harus mulai?|Ada kursus tentang pengembangan web?|Berapa harganya?\n";
        $sysPrompt .= "JANGAN menulis saran dari sudut pandang Anda (JANGAN menulis: 'Apakah Anda tertarik belajar X?'). Tuliskan: 'Saya tertarik belajar X, ada saran?'\n";

        $contents = $request->input('messages');

        $payload = [
            'system_instruction' => [
                'parts' => ['text' => $sysPrompt]
            ],
            'contents' => $contents
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:streamGenerateContent?alt=sse&key=' . $geminiApiKey;

        return response()->stream(function () use ($url, $payload) {
            // Bypass reverse proxy buffering (Nginx, Cloudflare)
            echo ":" . str_repeat(" ", 2048) . "\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
                $lines = explode("\n", $data);
                foreach ($lines as $line) {
                    if (strpos($line, 'data: ') === 0) {
                        $jsonStr = substr($line, 6);
                        $json = json_decode($jsonStr, true);
                        if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                            $chunkText = $json['candidates'][0]['content']['parts'][0]['text'];
                            echo "data: " . json_encode(['chunk' => $chunkText]) . "\n\n";
                            if (ob_get_level() > 0) ob_flush();
                            flush();
                        }
                    }
                }
                return strlen($data);
            });

            curl_exec($ch);
            curl_close($ch);

            echo "data: [DONE]\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();

        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive'
        ]);
    }
}
