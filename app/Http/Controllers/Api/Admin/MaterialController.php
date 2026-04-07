<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MaterialController extends Controller
{
    /**
     * Menyimpan materi baru (Bisa File PDF/Doc atau Link Video)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'title' => 'required|string|max:255',
            'type' => 'required|in:file,video',
            'access_tier' => 'required|in:all,premium', 
            'file' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx|max:20480',
            'link' => 'nullable|url'
        ]);

        $currentUser = $request->user();
        $event = Event::findOrFail($validated['event_id']);

        // 🔥 PROTEKSI MULTI-TENANT 🔥
        if ($currentUser->role === 'organizer' && $event->user_id !== $currentUser->id) {
            return response()->json([
                'success' => false, 
                'message' => 'Akses ditolak. Anda tidak bisa menambahkan materi ke event ini.'
            ], 403);
        }

        $data = [
            'event_id' => $validated['event_id'],
            'title' => $validated['title'],
            'type' => $validated['type'],
            'access_tier' => $validated['access_tier'],
        ];

        if ($validated['type'] === 'file') {
            if (!$request->hasFile('file')) {
                return response()->json(['success' => false, 'message' => 'File dokumen wajib diunggah'], 400);
            }
            $data['file_path'] = $request->file('file')->store('materials', 'public');
            $data['link'] = null; 
        } else {
            if (empty($validated['link'])) {
                return response()->json(['success' => false, 'message' => 'Link video wajib diisi'], 400);
            }
            $data['link'] = $validated['link'];
            $data['file_path'] = null; 
        }

        $material = Material::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Materi berhasil ditambahkan',
            'data' => $material
        ], 201);
    }

    /**
     * Menghapus materi
     */
    public function destroy(Request $request, $id)
    {
        $currentUser = $request->user();
        $material = Material::with('event')->findOrFail($id);

        // 🔥 PROTEKSI MULTI-TENANT 🔥
        if ($currentUser->role === 'organizer' && $material->event->user_id !== $currentUser->id) {
            return response()->json([
                'success' => false, 
                'message' => 'Akses ditolak. Ini bukan materi dari event Anda.'
            ], 403);
        }

        if ($material->type === 'file' && $material->file_path) {
            if (Storage::disk('public')->exists($material->file_path)) {
                Storage::disk('public')->delete($material->file_path);
            }
        }

        $material->delete();

        return response()->json([
            'success' => true,
            'message' => 'Materi berhasil dihapus'
        ]);
    }
}