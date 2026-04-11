<?php

namespace App\Http\Controllers\Api\Tryout\Skd;

use App\Http\Controllers\Controller;
use App\Models\SkdTryout;
use Illuminate\Http\Request;

class PublicSkdTryoutController extends Controller
{
    // Fetch Katalog untuk User (Hanya yang is_active = true)
    public function katalog()
    {
        $tryouts = SkdTryout::with('category')
            ->withCount('questions')
            ->where('is_active', true)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Katalog Tryout SKD',
            'data' => $tryouts
        ]);
    }
    
    // Nanti di sini ditambahkan function untuk show detail, daftar ujian, dll
}