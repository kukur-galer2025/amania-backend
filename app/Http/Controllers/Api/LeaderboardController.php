<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LeaderboardController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('filter', 'all');

        // Pisahkan logika filter agar bisa dipakai di whereHas dan withCount
        $filterQuery = function ($query) use ($filter) {
            $query->where('status', 'verified');

            if ($filter === 'month') {
                $query->whereMonth('created_at', Carbon::now()->month)
                      ->whereYear('created_at', Carbon::now()->year);
            } elseif ($filter === 'week') {
                $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
            }
        };

        // Mengambil user yang HANYA memiliki registrasi verified (whereHas)
        // Sekaligus menghitung jumlahnya (withCount)
        $leaders = User::whereHas('registrations', $filterQuery)
            ->withCount(['registrations' => $filterQuery])
            ->orderBy('registrations_count', 'desc')
            ->take(50)
            ->get(['id', 'name', 'email', 'avatar']);

        return response()->json([
            'success' => true,
            'filter' => $filter,
            'data' => $leaders
        ]);
    }
}