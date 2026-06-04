<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseSection;
use Illuminate\Http\Request;

class CourseSectionController extends Controller
{
    /**
     * TAMBAH SECTION KE KURSUS
     */
    public function store(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);

        if ($request->user()->role === 'creator' && $course->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'order' => 'nullable|integer',
        ]);

        $section = CourseSection::create([
            'course_id' => $course->id,
            'title'     => $request->title,
            'order'     => $request->order ?? (CourseSection::where('course_id', $course->id)->max('order') + 1),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Section berhasil ditambahkan!',
            'data'    => $section,
        ], 201);
    }

    /**
     * UPDATE SECTION
     */
    public function update(Request $request, $courseId, $sectionId)
    {
        $course = Course::findOrFail($courseId);
        if ($request->user()->role === 'creator' && $course->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $section = CourseSection::where('course_id', $courseId)->findOrFail($sectionId);

        $request->validate([
            'title' => 'required|string|max:255',
            'order' => 'nullable|integer',
        ]);

        $section->update([
            'title' => $request->title,
            'order' => $request->order ?? $section->order,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Section berhasil diperbarui!',
            'data'    => $section,
        ]);
    }

    /**
     * HAPUS SECTION (cascade delete lessons)
     */
    public function destroy(Request $request, $courseId, $sectionId)
    {
        $course = Course::findOrFail($courseId);
        if ($request->user()->role === 'creator' && $course->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $section = CourseSection::where('course_id', $courseId)->findOrFail($sectionId);
        $section->delete();

        return response()->json([
            'success' => true,
            'message' => 'Section dan semua lesson-nya berhasil dihapus!',
        ]);
    }
}
