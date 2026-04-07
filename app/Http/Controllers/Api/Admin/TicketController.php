<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Models\Event;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = $request->user();
        
        $queryEvent = Event::select('id', 'title')->orderBy('created_at', 'desc');
        $queryTicket = Registration::with(['user:id,name,email', 'event:id,title,start_time'])
                    ->where('status', 'verified')
                    ->whereNotNull('ticket_code');

        // 🔥 PROTEKSI MULTI-TENANT 🔥
        if ($currentUser->role === 'organizer') {
            $queryEvent->where('user_id', $currentUser->id);
            $queryTicket->whereHas('event', function($q) use ($currentUser) {
                $q->where('user_id', $currentUser->id);
            });
        }

        $events = $queryEvent->get();

        if ($request->has('event_id') && $request->event_id != '') {
            $queryTicket->where('event_id', $request->event_id);
        }

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $queryTicket->where(function($q) use ($search) {
                $q->where('ticket_code', 'like', "%{$search}%")
                  ->orWhereHas('user', function($qu) use ($search) {
                      $qu->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $tickets = $queryTicket->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil dimuat.',
            'events'  => $events,
            'data'    => $tickets
        ]);
    }

    public function check(Request $request)
    {
        $request->validate([
            'ticket_code' => 'required|string',
            'event_id'    => 'required'
        ]);

        $currentUser = $request->user();

        // Cek Event-nya dulu
        $eventToCheck = Event::find($request->event_id);
        if (!$eventToCheck) {
            return response()->json(['success' => false, 'message' => 'Event tidak valid.'], 404);
        }

        // 🔥 PROTEKSI SCAN: Mencegah Organizer menjaga/scan pintu event orang lain
        if ($currentUser->role === 'organizer' && $eventToCheck->user_id !== $currentUser->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak. Ini bukan event Anda.'], 403);
        }

        $ticket = Registration::with(['user:id,name,email', 'event:id,title,start_time'])
                    ->where('ticket_code', $request->ticket_code)
                    ->first();

        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Tiket tidak ditemukan di dalam sistem.', 'status_code' => 'not_found'], 404);
        }

        if ($ticket->event_id != $request->event_id) {
            return response()->json(['success' => false, 'message' => "SALAH EVENT! Tiket ini untuk program: {$ticket->event->title}.", 'status_code' => 'wrong_event', 'data' => $ticket], 400);
        }

        if ($ticket->status === 'pending') {
            return response()->json(['success' => false, 'message' => 'Pembayaran tiket belum divalidasi.', 'status_code' => 'pending', 'data' => $ticket], 400);
        }

        if ($ticket->status === 'rejected') {
            return response()->json(['success' => false, 'message' => 'Tiket ini telah dibatalkan/ditolak.', 'status_code' => 'rejected', 'data' => $ticket], 400);
        }

        return response()->json(['success' => true, 'message' => 'Tiket Valid!', 'status_code' => 'verified', 'data' => $ticket]);
    }
}