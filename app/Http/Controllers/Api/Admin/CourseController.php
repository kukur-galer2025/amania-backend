<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CourseLesson;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    /**
     * LIST SEMUA KURSUS (ADMIN)
     */
    public function index(Request $request)
    {
        $query = Course::with(['instructor', 'category'])
            ->withCount(['sections', 'enrollments', 'lessons', 'reviews'])
            ->withAvg('reviews', 'rating')
            ->latest();

        if ($request->user()->role === 'creator') {
            $query->where('user_id', $request->user()->id);
        }

        $courses = $query->get();

        return response()->json(['success' => true, 'data' => $courses]);
    }

    /**
     * DETAIL KURSUS + SECTIONS + LESSONS (ADMIN EDIT)
     */
    public function show(Request $request, $id)
    {
        $course = Course::with(['category', 'sections.lessons'])->findOrFail($id);

        if ($request->user()->role === 'creator' && $course->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json(['success' => true, 'data' => $course]);
    }

    /**
     * BUAT KURSUS BARU
     */
    public function store(Request $request)
    {
        $request->validate([
            'title'              => 'required|string|max:255',
            'course_category_id' => 'required|exists:course_categories,id',
            'description'        => 'nullable|string',
            'price'              => 'required|integer|min:0',
            'level'              => 'required|in:beginner,intermediate,advanced',
            'thumbnail'          => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
            'is_published'       => 'required|boolean',
        ]);

        $data = $request->only(['title', 'course_category_id', 'description', 'price', 'level', 'is_published']);
        $data['slug'] = Str::slug($request->title) . '-' . uniqid();
        $data['user_id'] = $request->user()->id;

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $request->file('thumbnail')->store('courses/thumbnails', 'public');
        }

        $course = Course::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Kursus berhasil ditambahkan!',
            'data'    => $course
        ], 201);
    }

    /**
     * UPDATE KURSUS + SYNC SECTIONS & LESSONS
     */
    public function update(Request $request, $id)
    {
        $course = Course::findOrFail($id);

        if ($request->user()->role === 'creator' && $course->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'title'              => 'required|string|max:255',
            'course_category_id' => 'required|exists:course_categories,id',
            'description'        => 'nullable|string',
            'price'              => 'required|integer|min:0',
            'level'              => 'required|in:beginner,intermediate,advanced',
            'thumbnail'          => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
            'is_published'       => 'required|boolean',
            'sections'           => 'nullable|string', // JSON string of sections + lessons
        ]);

        $data = $request->only(['title', 'course_category_id', 'description', 'price', 'level', 'is_published']);

        if ($request->title !== $course->title) {
            $data['slug'] = Str::slug($request->title) . '-' . uniqid();
        }

        if ($request->hasFile('thumbnail')) {
            if ($course->thumbnail && !Str::startsWith($course->thumbnail, ['http://', 'https://']) && Storage::disk('public')->exists($course->thumbnail)) {
                Storage::disk('public')->delete($course->thumbnail);
            }
            $data['thumbnail'] = $request->file('thumbnail')->store('courses/thumbnails', 'public');
        }

        $course->update($data);

        // ========================================
        // SYNC SECTIONS & LESSONS (dari JSON)
        // ========================================
        if ($request->has('sections')) {
            $sectionsData = json_decode($request->input('sections'), true);

            if (is_array($sectionsData)) {
                DB::transaction(function () use ($course, $sectionsData) {
                    $existingSectionIds = [];

                    foreach ($sectionsData as $sIdx => $sData) {
                        // Update existing section or create new
                        if (!empty($sData['id']) && $section = CourseSection::where('course_id', $course->id)->find($sData['id'])) {
                            $section->update([
                                'title' => $sData['title'] ?? 'Untitled Section',
                                'order' => $sIdx,
                            ]);
                        } else {
                            $section = CourseSection::create([
                                'course_id' => $course->id,
                                'title'     => $sData['title'] ?? 'Untitled Section',
                                'order'     => $sIdx,
                            ]);
                        }

                        $existingSectionIds[] = $section->id;

                        // Sync Lessons
                        $existingLessonIds = [];
                        foreach (($sData['lessons'] ?? []) as $lIdx => $lData) {
                            if (!empty($lData['id']) && $lesson = CourseLesson::where('course_section_id', $section->id)->find($lData['id'])) {
                                $lesson->update([
                                    'title'            => $lData['title'] ?? 'Untitled Lesson',
                                    'youtube_url'      => $lData['youtube_url'] ?? null,
                                    'duration_minutes' => $lData['duration_minutes'] ?? 0,
                                    'is_preview'       => $lData['is_preview'] ?? false,
                                    'order'            => $lIdx,
                                ]);
                            } else {
                                $lesson = CourseLesson::create([
                                    'course_section_id' => $section->id,
                                    'title'             => $lData['title'] ?? 'Untitled Lesson',
                                    'youtube_url'       => $lData['youtube_url'] ?? null,
                                    'duration_minutes'  => $lData['duration_minutes'] ?? 0,
                                    'is_preview'        => $lData['is_preview'] ?? false,
                                    'order'             => $lIdx,
                                ]);
                            }
                            $existingLessonIds[] = $lesson->id;
                        }

                        // Delete removed lessons
                        CourseLesson::where('course_section_id', $section->id)
                            ->whereNotIn('id', $existingLessonIds)
                            ->delete();
                    }

                    // Delete removed sections (cascades to lessons)
                    CourseSection::where('course_id', $course->id)
                        ->whereNotIn('id', $existingSectionIds)
                        ->delete();
                });
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Kursus berhasil diperbarui!',
            'data'    => $course->load('sections.lessons')
        ]);
    }

    /**
     * HAPUS KURSUS
     */
    public function destroy(Request $request, $id)
    {
        $course = Course::findOrFail($id);

        if ($request->user()->role === 'creator' && $course->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($course->thumbnail && !Str::startsWith($course->thumbnail, ['http://', 'https://']) && Storage::disk('public')->exists($course->thumbnail)) {
            Storage::disk('public')->delete($course->thumbnail);
        }

        $course->delete(); // cascades to sections, lessons

        return response()->json([
            'success' => true,
            'message' => 'Kursus berhasil dihapus!'
        ]);
    }
}
