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
    public function index(Request $request)
    {
        try {
            $search = $request->query('search');

            $query = Event::with('organizer')->latest();

            // 🔥 LOGIKA PENCARIAN BERDASARKAN QUERY 🔥
            if ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                      ->orWhereHas('organizer', function ($q) use ($search) {
                          $q->where('name', 'like', '%' . $search . '%');
                      });
            }

            $events = $query->get();

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
     * Menampilkan detail event khusus HALAMAN KATALOG (Bukan Ruang Kelas)
     */
    public function show(Request $request, $slug)
    {
        try {
            $event = Event::with(['materials', 'speakers', 'bankAccounts', 'organizer'])
                ->where('slug', $slug)
                ->first();

            if (!$event) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Maaf, event tidak ditemukan'
                ], 404);
            }

            // Inisialisasi default pendaftaran user (Hanya untuk keperluan visualisasi tombol UI)
            $event->user_registration = null; 

            // Cek jika user sedang login
            $user = auth('sanctum')->user();
            if ($user) {
                $event->user_registration = Registration::where('user_id', $user->id)
                    ->where('event_id', $event->id)
                    ->whereIn('status', ['pending', 'verified'])
                    ->first();
            }

            // 🔥 LOGIKA KEAMANAN KATALOG PUBLIK 🔥
            $event->join_link = null;
            $event->join_instructions = null;
            $event->certificate_link = null;

            // Kunci paksa semua materi (Hanya terlihat judul materi saja)
            $event->materials->transform(function ($material) {
                $material->link = null;
                $material->file_path = null;
                $material->is_locked = true; 
                return $material;
            });

            return response()->json([
                'success' => true,
                'message' => 'Detail katalog event berhasil ditemukan',
                'data'    => $event
            ], 200);

        } catch (\Exception $e) {
            Log::error("Error Detail Event Katalog: " . $e->getMessage()); 
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}