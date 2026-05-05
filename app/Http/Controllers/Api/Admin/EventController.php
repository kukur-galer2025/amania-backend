<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = $request->user();
        $query = Event::with('bankAccounts');

        if ($currentUser->role === 'organizer') {
            $query->where('user_id', $currentUser->id);
        }

        $events = $query->latest()->get();
        return response()->json(['success' => true, 'data' => $events]);
    }

    public function show(Request $request, $id)
    {
        $currentUser = $request->user();
        $event = Event::with(['materials', 'speakers', 'bankAccounts'])->findOrFail($id);

        if ($currentUser->role === 'organizer' && $event->user_id !== $currentUser->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
        }

        return response()->json(['success' => true, 'data' => $event]);
    }

    /**
     * MEMBUAT EVENT BARU (Hanya Draft Awal)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required',
            'venue' => 'required',
            'start_time' => 'required|date',
            'end_time' => 'required|date',
            'quota' => 'required|integer',
            'basic_price' => 'required|integer',
            'premium_price' => 'nullable|integer',
            
            // 🔥 PERBAIKAN: Ubah jadi nullable karena di form "Buat" tidak ada opsi QRIS 🔥
            'use_qris' => 'nullable|boolean', 
            
            'certificate_link' => 'nullable|url',
            'certificate_tier' => 'required|in:all,premium',
            
            'recording_link' => 'nullable|url',
            'recording_tier' => 'required|in:all,premium',
            
            'join_link' => 'nullable|url',
            'join_instructions' => 'nullable|string',
            
            'image' => 'nullable|image|mimes:jpg,png,jpeg,webp|max:10240',
            
            'banks' => 'nullable|array',
            'banks.*.bank_code' => 'required|string',
            'banks.*.account_number' => 'required|string',
            'banks.*.account_holder' => 'required|string',
        ]);

        return DB::transaction(function () use ($request, $validated) {
            if ($request->hasFile('image')) {
                $validated['image'] = $request->file('image')->store('events', 'public');
            }

            $validated['slug'] = Str::slug($request->title) . '-' . uniqid();
            $validated['user_id'] = $request->user()->id; 
            
            // 🔥 PERBAIKAN: Beri nilai default 'false' jika data use_qris tidak dikirim dari frontend 🔥
            $validated['use_qris'] = $request->has('use_qris') ? filter_var($request->use_qris, FILTER_VALIDATE_BOOLEAN) : false;
            
            $event = Event::create($validated);

            if ($request->has('banks') && is_array($request->banks) && count($request->banks) > 0) {
                $event->bankAccounts()->createMany($request->banks);
            }

            return response()->json([
                'success' => true, 
                'message' => 'Event berhasil dibuat',
                'data' => $event->load('bankAccounts')
            ], 201);
        });
    }

    /**
     * UPDATE EVENT (Halaman Edit Lengkap)
     */
    public function update(Request $request, $id)
    {
        $currentUser = $request->user();
        $event = Event::findOrFail($id);

        if ($currentUser->role === 'organizer' && $event->user_id !== $currentUser->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required',
            'venue' => 'required',
            'start_time' => 'required|date',
            'end_time' => 'required|date',
            'quota' => 'required|integer',
            'basic_price' => 'required|integer', 
            'premium_price' => 'nullable|integer',
            
            // 🔥 Di halaman Edit, QRIS wajib dikirim (bisa 0 atau 1) 🔥
            'use_qris' => 'required|boolean', 
            
            'certificate_link' => 'nullable|url',
            'certificate_tier' => 'required|in:all,premium',
            
            'recording_link' => 'nullable|url',
            'recording_tier' => 'required|in:all,premium',
            
            'join_link' => 'nullable|url',
            'join_instructions' => 'nullable|string',
            
            'image' => 'nullable|image|mimes:jpg,png,jpeg,webp|max:10240',
            
            'banks' => 'nullable|array',
            'banks.*.bank_code' => 'required|string',
            'banks.*.account_number' => 'required|string',
            'banks.*.account_holder' => 'required|string',
        ]);

        return DB::transaction(function () use ($request, $validated, $event) {
            if ($request->hasFile('image')) {
                if ($event->image) {
                    Storage::disk('public')->delete($event->image);
                }
                $validated['image'] = $request->file('image')->store('events', 'public');
            }

            if ($request->title !== $event->title) {
                $validated['slug'] = Str::slug($request->title) . '-' . uniqid();
            }
            
            $validated['use_qris'] = filter_var($request->use_qris, FILTER_VALIDATE_BOOLEAN);

            $event->update($validated);

            $event->bankAccounts()->delete();
            
            if ($request->has('banks') && is_array($request->banks) && count($request->banks) > 0) {
                $event->bankAccounts()->createMany($request->banks);
            }

            return response()->json([
                'success' => true, 
                'message' => 'Event berhasil diperbarui',
                'data' => $event->load('bankAccounts')
            ]);
        });
    }

    public function destroy(Request $request, $id)
    {
        $currentUser = $request->user();
        $event = Event::findOrFail($id);
        
        if ($currentUser->role === 'organizer' && $event->user_id !== $currentUser->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
        }

        if ($event->image) {
            Storage::disk('public')->delete($event->image);
        }

        $event->delete(); 

        return response()->json([
            'success' => true, 
            'message' => 'Event dan data terkait berhasil dihapus'
        ]);
    }
}