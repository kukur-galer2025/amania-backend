<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourseExam;
use App\Models\ExamQuestion;
use App\Models\Course;
use Illuminate\Http\Request;

class AdminExamController extends Controller
{
    // ==========================================
    // EXAM MANAGEMENT
    // ==========================================
    public function show($courseId, Request $request)
    {
        $course = Course::findOrFail($courseId);
        $user = $request->user();

        if ($user->role !== 'superadmin' && $course->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki izin.'], 403);
        }

        $exam = CourseExam::with('questions')->where('course_id', $courseId)->first();
        
        return response()->json([
            'success' => true,
            'data' => $exam
        ]);
    }

    public function storeOrUpdate(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);
        $user = $request->user();

        if ($user->role !== 'superadmin' && $course->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki izin.'], 403);
        }

        $request->validate([
            'title' => 'required|string',
            'passing_score' => 'required|integer|min:0|max:100',
            'description' => 'nullable|string'
        ]);

        $exam = CourseExam::updateOrCreate(
            ['course_id' => $courseId],
            [
                'title' => $request->title,
                'passing_score' => $request->passing_score,
                'description' => $request->description
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Data ujian berhasil disimpan.',
            'data' => $exam
        ]);
    }

    // ==========================================
    // QUESTION MANAGEMENT
    // ==========================================
    public function storeQuestion(Request $request, $examId)
    {
        $exam = CourseExam::with('course')->findOrFail($examId);
        $user = $request->user();

        if ($user->role !== 'superadmin' && $exam->course->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki izin.'], 403);
        }

        $request->validate([
            'question_text' => 'required|string',
            'option_a' => 'required|string',
            'option_b' => 'required|string',
            'option_c' => 'required|string',
            'option_d' => 'required|string',
            'correct_option' => 'required|in:A,B,C,D',
        ]);

        $question = ExamQuestion::create([
            'course_exam_id' => $examId,
            'question_text' => $request->question_text,
            'option_a' => $request->option_a,
            'option_b' => $request->option_b,
            'option_c' => $request->option_c,
            'option_d' => $request->option_d,
            'correct_option' => $request->correct_option,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Soal berhasil ditambahkan.',
            'data' => $question
        ]);
    }

    public function updateQuestion(Request $request, $questionId)
    {
        $question = ExamQuestion::with('exam.course')->findOrFail($questionId);
        $user = $request->user();

        if ($user->role !== 'superadmin' && $question->exam->course->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki izin.'], 403);
        }

        $request->validate([
            'question_text' => 'required|string',
            'option_a' => 'required|string',
            'option_b' => 'required|string',
            'option_c' => 'required|string',
            'option_d' => 'required|string',
            'correct_option' => 'required|in:A,B,C,D',
        ]);

        $question->update($request->only([
            'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Soal berhasil diupdate.',
            'data' => $question
        ]);
    }

    public function destroyQuestion($questionId, Request $request)
    {
        $question = ExamQuestion::with('exam.course')->findOrFail($questionId);
        $user = $request->user();

        if ($user->role !== 'superadmin' && $question->exam->course->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Tidak memiliki izin.'], 403);
        }

        $question->delete();

        return response()->json([
            'success' => true,
            'message' => 'Soal berhasil dihapus.'
        ]);
    }
}
