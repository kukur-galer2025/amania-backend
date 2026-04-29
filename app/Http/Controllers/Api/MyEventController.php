<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MyEventController extends Controller
{
    /**
     * Menampilkan detail event khusus ruang kelas (My Event)
     */
    public function show($slug)
    {
        try {
            $user = auth('sanctum')->user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Silakan login terlebih dahulu'], 401);
            }

            $event = Event::with(['materials', 'speakers', 'organizer'])
                ->where('slug', $slug)
                ->first();

            if (!$event) {
                return response()->json(['success' => false, 'message' => 'Event tidak ditemukan'], 404);
            }

            // Validasi ketat: Apakah user ini terdaftar dan sudah diverifikasi / pending
            $registration = Registration::where('user_id', $user->id)
                ->where('event_id', $event->id)
                ->whereIn('status', ['pending', 'verified'])
                ->first();

            if (!$registration) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak. Anda belum terdaftar di event ini.'], 403);
            }

            // Lampirkan data registrasi ke object event
            $event->user_registration = $registration;

            // ── LOGIKA PEMBATASAN AKSES MATERI DI RUANG KELAS PRIVAT ──
            if (in_array($registration->tier, ['free', 'basic']) || $registration->status === 'pending') {
                if ($event->certificate_tier === 'premium') {
                    $event->certificate_link = null;
                }

                $event->materials->transform(function ($material) {
                    if ($material->access_tier === 'premium') {
                        $material->link = null;
                        $material->file_path = null;
                        $material->is_locked = true; 
                    } else {
                        $material->is_locked = false;
                    }
                    return $material;
                });
            } else {
                // Jika user adalah Premium / VIP
                $event->materials->transform(function ($material) {
                    $material->is_locked = false;
                    return $material;
                });
            }

            return response()->json([
                'success' => true,
                'message' => 'Data ruang kelas berhasil diambil',
                'data'    => $event
            ], 200);

        } catch (\Exception $e) {
            Log::error("Error MyEvent Detail: " . $e->getMessage()); 
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan pada server'], 500);
        }
    }

    /**
     * API Khusus Force Download Poster (Aman di Lokal maupun Hosting cPanel)
     */
    public function downloadPoster($slug)
    {
        $event = Event::where('slug', $slug)->first();

        if (!$event || !$event->image) {
            return response()->json(['success' => false, 'message' => 'Poster tidak ditemukan'], 404);
        }

        // Cek lokasi fisik file gambar menggunakan facade Storage
        if (!Storage::disk('public')->exists($event->image)) {
            return response()->json(['success' => false, 'message' => 'File fisik tidak ditemukan di server'], 404);
        }

        // Force browser untuk melakukan download (Otomatis melempar header content-disposition attachment)
        return Storage::disk('public')->download($event->image, 'Poster-' . $event->slug . '.jpg');
    }
}