<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Models\Event;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = $request->user();

        // Siapkan query Transaksi dan Event
        $queryReg = Registration::with(['event:id,title,basic_price', 'user:id,name,email']);
        $queryEvent = Event::select('id', 'title')->orderBy('created_at', 'desc');

        // 🔥 PROTEKSI MULTI-TENANT 🔥
        if ($currentUser->role === 'organizer') {
            $queryReg->whereHas('event', function($q) use ($currentUser) {
                $q->where('user_id', $currentUser->id);
            });
            $queryEvent->where('user_id', $currentUser->id);
        }

        $transactions = $queryReg->latest()->get();

        // Hitung Statistik Keuangan Sesuai Filter Role
        $globalStats = [
            'total_revenue'  => (clone $queryReg)->where('status', 'verified')->sum('total_amount'),
            'pending_count'  => (clone $queryReg)->where('status', 'pending')->count(),
            'verified_count' => (clone $queryReg)->where('status', 'verified')->count(),
        ];

        // Ambil daftar event untuk dropdown
        $events = $queryEvent->get();

        return response()->json([
            'success' => true,
            'message' => 'Data riwayat transaksi berhasil diambil.',
            'stats'   => $globalStats, 
            'events'  => $events, 
            'data'    => $transactions
        ]);
    }
}