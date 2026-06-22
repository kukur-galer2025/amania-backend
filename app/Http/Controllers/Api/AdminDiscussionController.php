<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LessonComment;

class AdminDiscussionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Ambil diskusi parent_id = null
        $query = LessonComment::with([
            'user',
            'lesson.section.course',
            'replies.user'
        ])->whereNull('parent_id');

        // Jika bukan superadmin, hanya tampilkan dari kursus miliknya
        if ($user->role !== 'superadmin') {
            $query->whereHas('lesson.section.course', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        // Filter berdasarkan status balasan
        if ($request->has('status')) {
            $status = $request->status; // 'unanswered' or 'answered'
            if ($status === 'unanswered') {
                $query->whereDoesntHave('replies', function($q) use ($user) {
                    $q->where('user_id', $user->id); // Belum dibalas oleh kreator ini
                });
            } elseif ($status === 'answered') {
                $query->whereHas('replies', function($q) use ($user) {
                    $q->where('user_id', $user->id); // Sudah dibalas
                });
            }
        }

        $discussions = $query->orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $discussions]);
    }

    public function reply(Request $request, $id)
    {
        $request->validate([
            'body' => 'required|string|max:2000'
        ]);

        $parent = LessonComment::findOrFail($id);
        $user = $request->user();

        // Verify access
        if ($user->role !== 'superadmin' && $parent->lesson->section->course->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $reply = LessonComment::create([
            'course_lesson_id' => $parent->course_lesson_id,
            'user_id' => $user->id,
            'parent_id' => $parent->id,
            'body' => $request->body,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Balasan terkirim',
            'data' => $reply->load('user')
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $comment = LessonComment::findOrFail($id);
        $user = $request->user();

        // Verify access
        if ($user->role !== 'superadmin' && $comment->lesson->section->course->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $comment->delete(); // Ini otomatis menghapus replies jika di-cascade di DB, atau membiarkan
        return response()->json(['success' => true, 'message' => 'Diskusi dihapus.']);
    }
}
