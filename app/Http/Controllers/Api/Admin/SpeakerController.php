<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Speaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SpeakerController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'name'     => 'required|string|max:255',
            'role'     => 'required|string|max:255',
            // 🔥 UPDATE: MAX JADI 5120 (5 MB)
            'photo'    => 'nullable|image|mimes:jpg,png,jpeg,webp|max:5120', 
        ]);

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('speakers', 'public');
        }

        $speaker = Speaker::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pembicara berhasil ditambahkan',
            'data'    => $speaker
        ], 201);
    }

    public function destroy($id)
    {
        $speaker = Speaker::findOrFail($id);
        
        // Hapus file fisik foto jika ada
        if ($speaker->photo) {
            Storage::disk('public')->delete($speaker->photo);
        }
        
        $speaker->delete();

        return response()->json(['success' => true, 'message' => 'Pembicara dihapus']);
    }
}