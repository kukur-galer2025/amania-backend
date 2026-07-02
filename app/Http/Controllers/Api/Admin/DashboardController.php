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



        // 1. Hitung Total Pendapatan
        $totalPendapatan = (clone $regQuery)->where('status', 'verified')->sum('total_amount');

        // 2. Hitung Tiket Terjual
        $tiketTerjual = (clone $regQuery)->where('status', 'verified')->count();

        // 3. Hitung Menunggu Verifikasi
        $menungguVerifikasi = (clone $regQuery)->where('status', 'pending')->count();

        // 4. Hitung Total Peserta
        // - Superadmin: Lihat semua user yang role-nya 'user'
        $totalPeserta = User::where('role', 'user')->count();

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

        // Jika Creator, stats berbeda
        if ($currentUser->role === 'creator') {
            $totalCourses = \App\Models\Course::where('user_id', $currentUser->id)->count();
            $totalEproducts = \App\Models\EProduct::where('user_id', $currentUser->id)->count();

            // 1. Total Pendapatan Creator
            $courseRevenue = \App\Models\CourseEnrollment::whereHas('course', function ($q) use ($currentUser) {
                $q->where('user_id', $currentUser->id);
            })->whereIn('status', ['PAID', 'SETTLED', 'verified', 'berhasil'])->sum('amount');

            $eproductRevenue = \App\Models\EProductPurchase::whereHas('items.product', function ($q) use ($currentUser) {
                $q->where('user_id', $currentUser->id);
            })->whereIn('status', ['PAID', 'SETTLED', 'verified', 'berhasil'])->sum('amount');

            $totalPendapatan = $courseRevenue + $eproductRevenue;

            // 2. Total Peserta / Pembeli Unik Creator
            $courseBuyers = \App\Models\CourseEnrollment::whereHas('course', function ($q) use ($currentUser) {
                $q->where('user_id', $currentUser->id);
            })->whereIn('status', ['PAID', 'SETTLED', 'verified', 'berhasil'])->pluck('user_id');

            $eproductBuyers = \App\Models\EProductPurchase::whereHas('items.product', function ($q) use ($currentUser) {
                $q->where('user_id', $currentUser->id);
            })->whereIn('status', ['PAID', 'SETTLED', 'verified', 'berhasil'])->pluck('user_id');

            $totalPeserta = $courseBuyers->concat($eproductBuyers)->unique()->count();

            // 3. Aktivitas Pembelian Kursus & E-Produk Terbaru
            $recentCourses = \App\Models\CourseEnrollment::with(['user', 'course'])
                ->whereHas('course', function ($q) use ($currentUser) {
                    $q->where('user_id', $currentUser->id);
                })
                ->latest()
                ->take(5)
                ->get()
                ->map(function ($tx) {
                    $status = strtolower($tx->status);
                    return [
                        'id' => 'crs_' . $tx->id,
                        'name' => $tx->user ? $tx->user->name : 'Peserta Kursus',
                        'event' => 'Kursus: ' . ($tx->course ? $tx->course->title : '-'),
                        'status' => in_array($status, ['paid', 'settled', 'verified', 'berhasil']) ? 'verified' : ($status === 'unpaid' ? 'pending' : $status),
                        'date' => $tx->created_at->diffForHumans(),
                        'created_at' => $tx->created_at,
                    ];
                });

            $recentEproducts = \App\Models\EProductPurchase::with(['buyer', 'items.product'])
                ->whereHas('items.product', function ($q) use ($currentUser) {
                    $q->where('user_id', $currentUser->id);
                })
                ->latest()
                ->take(5)
                ->get()
                ->map(function ($tx) {
                    $status = strtolower($tx->status);
                    $productTitle = $tx->items->first() && $tx->items->first()->product ? $tx->items->first()->product->title : 'E-Produk';
                    return [
                        'id' => 'ep_' . $tx->id,
                        'name' => $tx->buyer ? $tx->buyer->name : 'Pembeli E-Produk',
                        'event' => 'E-Produk: ' . $productTitle,
                        'status' => in_array($status, ['paid', 'settled', 'verified', 'berhasil']) ? 'verified' : ($status === 'unpaid' ? 'pending' : $status),
                        'date' => $tx->created_at->diffForHumans(),
                        'created_at' => $tx->created_at,
                    ];
                });

            $recentActivities = $recentCourses->concat($recentEproducts)
                ->sortByDesc('created_at')
                ->take(5)
                ->values()
                ->map(function ($item) {
                    unset($item['created_at']);
                    return $item;
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'is_creator' => true,
                    'user_name' => $currentUser->name,
                    'total_courses' => $totalCourses,
                    'total_eproducts' => $totalEproducts,
                    'total_pendapatan' => $totalPendapatan,
                    'pendapatan_kursus' => $courseRevenue,
                    'pendapatan_eproduk' => $eproductRevenue,
                    'total_peserta' => $totalPeserta,
                    'tiket_terjual' => 0,
                    'menunggu_verifikasi' => 0,
                    'recent_registrations' => $recentActivities
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'is_creator' => false,
                'user_name' => $currentUser->name,
                'total_pendapatan' => $totalPendapatan,
                'total_peserta' => $totalPeserta,
                'tiket_terjual' => $tiketTerjual,
                'menunggu_verifikasi' => $menungguVerifikasi,
                'recent_registrations' => $recentRegistrations
            ]
        ]);
    }
}