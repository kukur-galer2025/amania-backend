<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLessonProgress;
use App\Models\CourseReview;
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

        if (!$isEnrolled) {
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

        // Return flat structure matching LearnClient.tsx expectations
        return response()->json([
            'success' => true,
            'data' => [
                'title'             => $course->title,
                'slug'              => $course->slug,
                'sections'          => $course->sections,
                'instructor'        => $course->instructor,
                'completed_lessons' => $completedLessonIds,
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

        if (!$isEnrolled) {
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

        if (!$isEnrolled) {
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
}
