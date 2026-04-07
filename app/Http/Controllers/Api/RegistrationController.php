<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\AdminAlertNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    public function myRegistrations()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Silakan login terlebih dahulu.'], 401);
        }

        $registrations = Registration::with('event')->where('user_id', $user->id)->latest()->get();
        return response()->json(['success' => true, 'data' => $registrations]);
    }

    public function store(Request $request)
    {
        $user = auth()->user(); 
        if (!$user) {
            return response()->json([
                'success' => false, 
                'message' => 'Akses ditolak. Sesi Anda tidak valid atau belum login.'
            ], 401);
        }

        $request->validate([
            'event_id'      => 'required|exists:events,id',
            'tier'          => 'required|in:free,premium',
            'payment_proof' => 'nullable|image|mimes:jpg,jpeg,png|max:5120'
        ]);

        $event = Event::findOrFail($request->event_id);

        if ($event->end_time < now()) {
            return response()->json(['success' => false, 'message' => 'Pendaftaran gagal. Kelas ini telah berakhir.'], 400);
        }

        $existing = Registration::where('user_id', $user->id)->where('event_id', $event->id)->first();
        if ($existing) {
            return response()->json([
                'success' => false, 
                'message' => 'Anda sudah terdaftar di kelas ini dengan tiket ' . strtoupper($existing->tier) . '. Anda tidak dapat mendaftar lagi atau mengubah kategori tiket.'
            ], 400); 
        }

        $currentParticipants = Registration::where('event_id', $event->id)
                                           ->whereIn('status', ['verified', 'pending'])
                                           ->count();
                                           
        if ($currentParticipants >= $event->quota && $event->quota > 0) {
            return response()->json(['success' => false, 'message' => 'Maaf, kuota untuk kelas ini sudah penuh!'], 400);
        }

        $priceToPay = $request->tier === 'premium' ? ($event->premium_price ?? $event->basic_price) : $event->basic_price;
        
        // 🔥 LOGIKA PERBAIKAN: Default status jika Rp 0
        $status = 'verified'; 
        $returnMessage = 'Pendaftaran kelas gratis berhasil diselesaikan!';
        $paymentPath = null;

        // 🔥 LOGIKA JIKA BERBAYAR (> 0)
        if ($priceToPay > 0) {
            if (!$request->hasFile('payment_proof')) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Pendaftaran gagal. Bukti pembayaran wajib diunggah karena kelas ini berbayar.'
                ], 400);
            }
            
            $paymentPath = $request->file('payment_proof')->store('payments', 'public');
            $status = 'pending';
            $returnMessage = 'Pembayaran berhasil diunggah. Menunggu konfirmasi Admin Amania (Maks 1x24 Jam).';
        }

        $eventPrefix = str_pad($event->id, 3, '0', STR_PAD_LEFT);
        $ticketCode = "AM-". date('Y') . "-{$eventPrefix}-" . strtoupper(Str::random(5)); // AM untuk Amania

        $registration = Registration::create([
            'ticket_code'   => $ticketCode,
            'user_id'       => $user->id,
            'event_id'      => $event->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'payment_proof' => $paymentPath,
            'status'        => $status, // Akan masuk 'verified' otomatis jika Rp 0
            'tier'          => $request->tier,
            'total_amount'  => $priceToPay
        ]);

        // 🔥 TRIGGER NOTIFIKASI KE ADMIN 🔥
        $admins = User::where('role', 'admin')->get();
        if ($admins->count() > 0) {
            Notification::send($admins, new AdminAlertNotification([
                'title' => 'Pendaftaran Baru',
                'message' => "{$user->name} mendaftar di program {$event->title}.",
                'status' => $status,
                'url' => '/admin/registrations'
            ]));
        }

        return response()->json([
            'success' => true,
            'message' => $returnMessage,
            'data'    => $registration
        ], 201);
    }

    public function reuploadProof(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Silakan login terlebih dahulu.'], 401);
        }

        $request->validate([
            'payment_proof' => 'required|image|mimes:jpg,jpeg,png|max:5120'
        ]);
        
        $registration = Registration::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        if ($registration->status !== 'rejected') {
            return response()->json([
                'success' => false, 
                'message' => 'Hanya pendaftaran yang ditolak yang dapat mengunggah ulang bukti.'
            ], 400);
        }

        $paymentPath = $request->file('payment_proof')->store('payments', 'public');

        $registration->update([
            'payment_proof' => $paymentPath,
            'status' => 'pending',
            'rejection_reason' => null
        ]);

        $admins = User::where('role', 'admin')->get();
        if ($admins->count() > 0) {
            Notification::send($admins, new AdminAlertNotification([
                'title' => 'Upload Bukti Baru',
                'message' => "{$user->name} mengunggah ulang bukti pembayaran.",
                'status' => 'pending',
                'url' => '/admin/registrations'
            ]));
        }

        return response()->json([
            'success' => true,
            'message' => 'Bukti berhasil diunggah ulang! Menunggu verifikasi Admin Amania.',
            'data' => $registration
        ]);
    }
}