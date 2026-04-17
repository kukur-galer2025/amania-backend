<?php

namespace App\Http\Controllers\Api\Tryout\Skd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SkdTryout;
use App\Models\SkdTransaction;

class SkdExamController extends Controller
{
    public function getQuestions(Request $request, $slug)
    {
        $user = $request->user();

        // 1. Ambil tryout beserta soal dan relasi sub kategorinya
        // Sesuaikan nama relasi 'subCategory' dengan yang ada di model SkdQuestion Anda
        $tryout = SkdTryout::with(['questions' => function($query) {
            $query->with('subCategory') // <-- Mengambil data Sub Kategori
                  ->orderBy('id', 'asc');
        }])->where('slug', $slug)->first();

        if (!$tryout) {
            return response()->json(['success' => false, 'message' => 'Paket Tryout tidak ditemukan.'], 404);
        }

        // 2. Validasi Keamanan (Pastikan LUNAS)
        $hasAccess = SkdTransaction::where('user_id', $user->id)
            ->where('skd_tryout_id', $tryout->id)
            ->where('status', 'PAID')
            ->exists();

        if (!$hasAccess && $tryout->price > 0) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak. Anda belum membeli paket ini.'], 403);
        }

        // 3. 🔥 KEAMANAN TINGKAT TINGGI: Hapus kunci jawaban dan skor sebelum dikirim ke frontend! 🔥
        $tryout->questions->each(function($question) {
            $question->makeHidden([
                'answer_key', 
                'score_a', 'score_b', 'score_c', 'score_d', 'score_e', 
                'explanation', 
                'created_at', 'updated_at'
            ]);
        });

        // 4. Kembalikan respons
        return response()->json([
            'success' => true,
            'message' => 'Berhasil memuat soal.',
            'data' => [
                'tryout'    => $tryout->only(['id', 'title', 'duration_minutes']),
                'questions' => $tryout->questions
            ]
        ]);
    }
}