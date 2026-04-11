<?php

namespace App\Http\Controllers\Api\Admin\Tryout\Skd;

use App\Http\Controllers\Controller;
use App\Models\SkdQuestion;
use Illuminate\Http\Request;

class SkdQuestionController extends Controller
{
    // Melihat daftar soal (bisa difilter berdasarkan ID Tryout)
    public function index(Request $request)
    {
        $query = SkdQuestion::with(['tryout', 'subCategory'])->latest();

        // Filter dari Frontend: ?skd_tryout_id=1
        if ($request->has('skd_tryout_id')) {
            $query->where('skd_tryout_id', $request->skd_tryout_id);
        }

        $questions = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar Soal SKD',
            'data' => $questions
        ]);
    }

    // Menambahkan soal baru
    public function store(Request $request)
    {
        $request->validate([
            'skd_tryout_id' => 'required|exists:skd_tryouts,id',
            'skd_question_sub_category_id' => 'required|exists:skd_question_sub_categories,id',
            'main_category' => 'required|in:twk,tiu,tkp',
            'question_text' => 'required|string',
            
            'option_a' => 'nullable|string',
            'option_b' => 'nullable|string',
            'option_c' => 'nullable|string',
            'option_d' => 'nullable|string',
            'option_e' => 'nullable|string',
            
            // Score minimal 0, maksimal 5
            'score_a' => 'required|integer|min:0|max:5',
            'score_b' => 'required|integer|min:0|max:5',
            'score_c' => 'required|integer|min:0|max:5',
            'score_d' => 'required|integer|min:0|max:5',
            'score_e' => 'required|integer|min:0|max:5',
        ]);

        $question = SkdQuestion::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Soal SKD berhasil ditambahkan',
            'data' => $question
        ], 201);
    }

    // Melihat detail 1 soal
    public function show($id)
    {
        $question = SkdQuestion::with(['tryout', 'subCategory'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail Soal SKD',
            'data' => $question
        ]);
    }

    // Mengedit soal
    public function update(Request $request, $id)
    {
        $question = SkdQuestion::findOrFail($id);

        $request->validate([
            'skd_tryout_id' => 'required|exists:skd_tryouts,id',
            'skd_question_sub_category_id' => 'required|exists:skd_question_sub_categories,id',
            'main_category' => 'required|in:twk,tiu,tkp',
            'question_text' => 'required|string',
            
            'score_a' => 'required|integer|min:0|max:5',
            'score_b' => 'required|integer|min:0|max:5',
            'score_c' => 'required|integer|min:0|max:5',
            'score_d' => 'required|integer|min:0|max:5',
            'score_e' => 'required|integer|min:0|max:5',
        ]);

        $question->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Soal SKD berhasil diubah',
            'data' => $question
        ]);
    }

    // Menghapus soal
    public function destroy($id)
    {
        $question = SkdQuestion::findOrFail($id);
        $question->delete();

        return response()->json([
            'success' => true,
            'message' => 'Soal SKD berhasil dihapus'
        ]);
    }
}