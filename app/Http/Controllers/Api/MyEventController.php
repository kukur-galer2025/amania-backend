<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Material; // 🔥 PERBAIKAN: Menggunakan model Material (bukan EventMaterial)
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
     * API Khusus Force Download Poster 
     */
    public function downloadPoster($slug)
    {
        try {
            $event = Event::where('slug', $slug)->first();

            if (!$event || !$event->image) {
                return response()->json(['success' => false, 'message' => 'Poster tidak ditemukan'], 404);
            }

            if (!Storage::disk('public')->exists($event->image)) {
                return response()->json(['success' => false, 'message' => 'File fisik poster tidak ditemukan di server'], 404);
            }

            return Storage::disk('public')->download($event->image, 'Poster-' . $event->slug . '.jpg');

        } catch (\Exception $e) {
            Log::error("Download Poster Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Sistem gagal mengunduh poster: ' . $e->getMessage()], 500);
        }
    }

    /**
     * API Khusus Force Download Material/Modul
     */
    public function downloadMaterial($id)
    {
        try {
            $user = auth('sanctum')->user();
            
            if (!$user) {
                 return response()->json(['success' => false, 'message' => 'Autentikasi diperlukan'], 401);
            }

            // 🔥 PERBAIKAN: Menggunakan model Material 🔥
            $material = Material::find($id);

            if (!$material || !$material->file_path) {
                return response()->json(['success' => false, 'message' => 'Modul tidak ditemukan atau bukan berupa file fisik'], 404);
            }
            
            // --- Proteksi Akses ---
            $registration = Registration::where('user_id', $user->id)
                ->where('event_id', $material->event_id)
                ->whereIn('status', ['pending', 'verified'])
                ->first();

            if (!$registration) {
                 return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses ke event ini.'], 403);
            }

            if ($material->access_tier === 'premium' && !in_array($registration->tier, ['premium', 'vip'])) {
                 return response()->json(['success' => false, 'message' => 'Materi ini eksklusif untuk member Premium/VIP.'], 403);
            }

            // Mengecek file fisik
            if (!Storage::disk('public')->exists($material->file_path)) {
                return response()->json(['success' => false, 'message' => 'Maaf, file fisik modul belum diunggah atau hilang dari server.'], 404);
            }

            $extension = pathinfo($material->file_path, PATHINFO_EXTENSION);
            $cleanTitle = preg_replace('/[^a-zA-Z0-9_-]/', '-', $material->title); 
            $downloadName = $cleanTitle . '.' . ($extension ?: 'pdf');

            return Storage::disk('public')->download($material->file_path, $downloadName);

        } catch (\Exception $e) {
            // 🔥 PERBAIKAN: Menangkap error agar tidak mengembalikan halaman HTML Whoops ke React 🔥
            Log::error("Download Material Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Sistem gagal mengunduh modul: ' . $e->getMessage()], 500);
        }
    }
}