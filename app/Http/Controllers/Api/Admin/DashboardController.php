<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Registration;
use App\Models\Event;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = $request->user();

        // Siapkan base query untuk Registrasi
        $regQuery = Registration::query();

        // 🔥 PROTEKSI MULTI-TENANT 🔥
        if ($currentUser->role === 'organizer') {
            $regQuery->whereHas('event', function ($q) use ($currentUser) {
                $q->where('user_id', $currentUser->id);
            });
        }

        // 1. Hitung Total Pendapatan
        $totalPendapatan = (clone $regQuery)->where('status', 'verified')->sum('total_amount');

        // 2. Hitung Tiket Terjual
        $tiketTerjual = (clone $regQuery)->where('status', 'verified')->count();

        // 3. Hitung Menunggu Verifikasi
        $menungguVerifikasi = (clone $regQuery)->where('status', 'pending')->count();

        // 4. Hitung Total Peserta
        // - Superadmin: Lihat semua user yang role-nya 'user'
        // - Organizer: Hanya hitung peserta unik yang pernah daftar di event-nya
        if ($currentUser->role === 'superadmin') {
            $totalPeserta = User::where('role', 'user')->count();
        } else {
            $totalPeserta = (clone $regQuery)->distinct('user_id')->count('user_id');
        }

        // 5. Ambil 5 Pendaftaran Terbaru
        $recentRegistrations = (clone $regQuery)
            ->with('event')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($reg) {
                return [
                    'id' => $reg->id,
                    'name' => $reg->name,
                    'event' => $reg->event ? $reg->event->title : 'Event tidak ditemukan',
                    'status' => $reg->status,
                    'date' => $reg->created_at->diffForHumans(), 
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'total_pendapatan' => $totalPendapatan,
                'total_peserta' => $totalPeserta,
                'tiket_terjual' => $tiketTerjual,
                'menunggu_verifikasi' => $menungguVerifikasi,
                'recent_registrations' => $recentRegistrations
            ]
        ]);
    }
}