<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LessonComment;
use App\Models\CourseLesson;
use App\Models\CourseEnrollment;
use Illuminate\Http\Request;

class LessonCommentController extends Controller
{
    public function index($lessonId, Request $request)
    {
        $lesson = CourseLesson::with('section.course')->findOrFail($lessonId);
        $user = $request->user();

        // Check access
        $courseId = $lesson->section->course->id;
        $isCreator = $lesson->section->course->user_id === $user->id;
        $isSuperadmin = $user->role === 'superadmin';
        
        $isEnrolled = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->whereIn('status', ['PAID', 'success', 'SETTLED'])
            ->exists();

        if (!$isEnrolled && !$isCreator && !$isSuperadmin) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $comments = LessonComment::with(['user:id,name,role', 'replies.user:id,name,role'])
            ->where('course_lesson_id', $lessonId)
            ->whereNull('parent_id')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $comments
        ]);
    }

    public function store(Request $request, $lessonId)
    {
        $request->validate([
            'body' => 'required|string',
            'parent_id' => 'nullable|exists:lesson_comments,id'
        ]);

        $lesson = CourseLesson::with('section.course')->findOrFail($lessonId);
        $user = $request->user();

        // Check access
        $courseId = $lesson->section->course->id;
        $isCreator = $lesson->section->course->user_id === $user->id;
        $isSuperadmin = $user->role === 'superadmin';
        
        $isEnrolled = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $courseId)
            ->whereIn('status', ['PAID', 'success', 'SETTLED'])
            ->exists();

        if (!$isEnrolled && !$isCreator && !$isSuperadmin) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $comment = LessonComment::create([
            'user_id' => $user->id,
            'course_lesson_id' => $lessonId,
            'parent_id' => $request->parent_id,
            'body' => $request->body,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Komentar berhasil ditambahkan.',
            'data' => $comment->load('user:id,name,role')
        ]);
    }

    public function destroy($id, Request $request)
    {
        $comment = LessonComment::with('lesson.section.course')->findOrFail($id);
        $user = $request->user();

        $isCreator = $comment->lesson->section->course->user_id === $user->id;
        $isSuperadmin = $user->role === 'superadmin';
        $isOwner = $comment->user_id === $user->id;

        if (!$isOwner && !$isCreator && !$isSuperadmin) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki izin.'], 403);
        }

        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Komentar berhasil dihapus.'
        ]);
    }
}
