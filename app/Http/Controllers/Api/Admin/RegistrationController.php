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



        if ($request->has('event_id') && $request->event_id != 'all') {
            $query->where('event_id', $request->event_id);
        }

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
        $reg = Registration::with(['event', 'user'])->findOrFail($id);



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

        // TRIGGER NOTIFIKASI KE MEMBER
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

        // TRIGGER NOTIFIKASI KE MEMBER
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

    /**
     * EXPORT DATA PENDAFTAR KE CSV
     */
    public function export(Request $request, $eventId)
    {
        $query = Registration::with(['user', 'event']);
        
        if ($eventId !== 'all') {
            $query->where('event_id', $eventId);
        }

        $registrations = $query->get();

        $filename = "Registrants_{$eventId}_" . date('Ymd_His') . ".csv";

        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $columns = ['ID', 'Nama Lengkap', 'Email', 'No WA/HP', 'Nama Program', 'Kode Tiket', 'Tipe Tiket', 'Status', 'Nominal Bayar', 'Tanggal Daftar'];

        $callback = function() use($registrations, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($registrations as $reg) {
                fputcsv($file, [
                    $reg->id,
                    $reg->user->name ?? 'Unknown',
                    $reg->user->email ?? 'Unknown',
                    $reg->user->phone ?? '-',
                    $reg->event->title ?? 'Unknown',
                    $reg->ticket_code ?? '-',
                    strtoupper($reg->tier ?? 'BASIC'),
                    strtoupper($reg->status),
                    $reg->total_amount,
                    $reg->created_at->format('Y-m-d H:i:s')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}