<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EventController extends Controller
{
    /**
     * Menampilkan daftar semua event (Katalog)
     */
    public function index()
    {
        try {
            // 🔥 PERBAIKAN: Tarik juga relasi organizer agar di frontend bisa tampil "Diselenggarakan oleh: X"
            $events = Event::with('organizer')->latest()->get();

            return response()->json([
                'success' => true,
                'message' => 'Daftar event berhasil diambil',
                'data'    => $events
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data event',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan detail event berdasarkan slug (Halaman Ruang Kelas Member)
     */
    public function show(Request $request, $slug)
    {
        try {
            // 🔥 PERBAIKAN: Tambahkan 'organizer' ke Eager Loading
            $event = Event::with(['materials', 'speakers', 'bankAccounts', 'organizer'])
                ->where('slug', $slug)
                ->first();

            if (!$event) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Maaf, event tidak ditemukan'
                ], 404);
            }

            // 🔥 LOGIKA PEMBATASAN AKSES FREEMIUM 🔥
            // Cek apakah user sedang login dan cek tiketnya
            $user = auth('sanctum')->user();
            
            if ($user) {
                // Cari pendaftaran user untuk event ini yang statusnya verified
                $registration = Registration::where('user_id', $user->id)
                                            ->where('event_id', $event->id)
                                            ->where('status', 'verified')
                                            ->first();

                // Jika punya tiket, periksa apakah tiketnya Basic (Free/Basic)
                if ($registration && ($registration->tier === 'free' || $registration->tier === 'basic')) {
                    
                    // 1. Sembunyikan Link Sertifikat jika sertifikat diset Premium
                    if ($event->certificate_tier === 'premium') {
                        $event->certificate_link = null; 
                    }

                    // 2. Sembunyikan Link Materi/File jika materi diset Premium
                    // Kita manipulasi koleksi materials agar link-nya kosong, tapi judulnya tetap ada
                    $event->materials->transform(function ($material) {
                        if ($material->access_tier === 'premium') {
                            $material->link = null;
                            $material->file_path = null;
                            $material->is_locked = true; // Flag untuk UI Frontend
                        } else {
                            $material->is_locked = false;
                        }
                        return $material;
                    });
                } 
                // Jika tiketnya Premium/VIP, semua is_locked = false
                else if ($registration && $registration->tier === 'premium') {
                    $event->materials->transform(function ($material) {
                        $material->is_locked = false;
                        return $material;
                    });
                }
            } else {
                // Jika user tidak login (Guest), kunci semua materi premium
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
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail event berhasil ditemukan',
                'data'    => $event
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}