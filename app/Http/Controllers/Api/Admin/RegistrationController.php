<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\UserStatusNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    /**
     * MENAMPILKAN SEMUA PENDAFTAR KE ADMIN / ORGANIZER
     */
    public function index(Request $request)
    {
        $currentUser = $request->user();
        $query = Registration::with(['event', 'user']);

        // 🔥 LOGIKA MULTI-TENANT: ORGANIZER HANYA LIHAT EVENT MILIKNYA 🔥
        if ($currentUser->role === 'organizer') {
            $query->whereHas('event', function ($q) use ($currentUser) {
                $q->where('user_id', $currentUser->id);
            });
        }

        // Filter berdasarkan event jika ada
        if ($request->has('event_id') && $request->event_id != 'all') {
            $query->where('event_id', $request->event_id);
        }

        // Filter pencarian berdasarkan nama user atau kode tiket
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('ticket_code', 'like', "%{$search}%")
                  ->orWhereHas('user', function($qu) use ($search) {
                      $qu->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $registrations = $query->latest()->get();
        
        return response()->json([
            'success' => true, 
            'data' => $registrations
        ]);
    }

    /**
     * ADMIN/ORGANIZER MENYETUJUI PEMBAYARAN
     */
    public function verify(Request $request, $id)
    {
        $currentUser = $request->user();
        // Eager load event dan user agar data notifikasi lengkap
        $reg = Registration::with(['event', 'user'])->findOrFail($id);

        // 🔥 PROTEKSI: Cegah Organizer memverifikasi event milik orang lain 🔥
        if ($currentUser->role === 'organizer' && $reg->event->user_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda bukan penyelenggara program ini.'
            ], 403);
        }

        if ($reg->status === 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Pendaftaran ini sudah diverifikasi sebelumnya.'
            ], 400);
        }

        // Generate ticket_code jika kosong
        $ticketCode = $reg->ticket_code;
        if (empty($ticketCode)) {
            $eventPrefix = str_pad($reg->event_id, 3, '0', STR_PAD_LEFT);
            $randomStr = strtoupper(Str::random(5));
            $ticketCode = "AM-" . date('Y') . "-{$eventPrefix}-{$randomStr}";
        }

        $reg->update([
            'status' => 'verified',
            'ticket_code' => $ticketCode,
            'rejection_reason' => null 
        ]);

        // 🔥 TRIGGER NOTIFIKASI KE MEMBER 🔥
        if ($reg->user) {
            $reg->user->notify(new UserStatusNotification($reg, 'verified'));
        }

        return response()->json([
            'success' => true, 
            'message' => 'Pembayaran berhasil diverifikasi. Notifikasi telah dikirim ke member.',
            'data' => $reg
        ]);
    }

    /**
     * ADMIN/ORGANIZER MENOLAK PEMBAYARAN DENGAN ALASAN
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|min:5'
        ]);

        $currentUser = $request->user();
        $reg = Registration::with(['event', 'user'])->findOrFail($id);

        // 🔥 PROTEKSI: Cegah Organizer menolak event milik orang lain 🔥
        if ($currentUser->role === 'organizer' && $reg->event->user_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda bukan penyelenggara program ini.'
            ], 403);
        }

        if ($reg->status === 'verified') {
             return response()->json([
                 'success' => false,
                 'message' => 'Tidak dapat menolak yang sudah diverifikasi. Batalkan verifikasi dulu.'
             ], 400);
        }

        $reg->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason 
        ]);

        // 🔥 TRIGGER NOTIFIKASI KE MEMBER 🔥
        if ($reg->user) {
            $reg->user->notify(new UserStatusNotification($reg, 'rejected'));
        }

        return response()->json([
            'success' => true, 
            'message' => 'Pendaftaran ditolak. Alasan telah dikirim ke notifikasi member.',
            'data' => $reg
        ]);
    }

    /**
     * MENGEMBALIKAN STATUS KE PENDING (RESET)
     */
    public function markAsPending(Request $request, $id)
    {
        $currentUser = $request->user();
        $reg = Registration::with(['event'])->findOrFail($id);
        
        // 🔥 PROTEKSI MULTI-TENANT 🔥
        if ($currentUser->role === 'organizer' && $reg->event->user_id !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda bukan penyelenggara program ini.'
            ], 403);
        }

        if ($reg->status === 'pending') {
             return response()->json([
                 'success' => false,
                 'message' => 'Status pendaftaran memang sudah pending.'
             ], 400);
        }

        $reg->update([
            'status' => 'pending',
            'rejection_reason' => null
        ]);

        return response()->json([
            'success' => true, 
            'message' => 'Status pendaftaran dikembalikan ke Pending (Review).',
            'data' => $reg
        ]);
    }
}