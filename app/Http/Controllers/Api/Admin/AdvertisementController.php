<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use App\Helpers\ImageHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdvertisementController extends Controller
{
    public function index()
    {
        $ads = Advertisement::orderBy('order', 'asc')->orderBy('created_at', 'desc')->get();
        return response()->json([
            'success' => true,
            'data' => $ads
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'placement' => 'required',
            'title' => 'required|string|max:255',
            'image' => 'required|image|max:10240', // max 10MB
            'url' => 'nullable|url|max:255',
            'is_active' => 'boolean',
            'order' => 'integer'
        ]);

        $imagePath = ImageHelper::compressAndStore($request->file('image'), 'advertisements', 1200, 900, 80);

        // Parse placement: accept JSON string or comma-separated
        $placement = $this->parsePlacement($request->placement);

        $ad = Advertisement::create([
            'placement' => $placement,
            'title' => $request->title,
            'image_path' => $imagePath,
            'url' => $request->url,
            'is_active' => $request->is_active ?? true,
            'order' => $request->order ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Iklan berhasil ditambahkan.',
            'data' => $ad
        ]);
    }

    public function update(Request $request, $id)
    {
        $ad = Advertisement::findOrFail($id);

        $request->validate([
            'placement' => 'required',
            'title' => 'required|string|max:255',
            'image' => 'nullable|image|max:10240', // max 10MB
            'url' => 'nullable|url|max:255',
            'is_active' => 'boolean',
            'order' => 'integer'
        ]);

        // Parse placement: accept JSON string or comma-separated
        $placement = $this->parsePlacement($request->placement);

        $data = [
            'placement' => $placement,
            'title' => $request->title,
            'url' => $request->url,
            'is_active' => $request->is_active ?? $ad->is_active,
            'order' => $request->order ?? $ad->order,
        ];

        if ($request->hasFile('image')) {
            if ($ad->image_path && Storage::disk('public')->exists($ad->image_path)) {
                Storage::disk('public')->delete($ad->image_path);
            }
            $data['image_path'] = ImageHelper::compressAndStore($request->file('image'), 'advertisements', 1200, 900, 80);
        }

        $ad->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Iklan berhasil diperbarui.',
            'data' => $ad
        ]);
    }

    public function toggleActive($id)
    {
        $ad = Advertisement::findOrFail($id);
        $ad->is_active = !$ad->is_active;
        $ad->save();

        return response()->json([
            'success' => true,
            'message' => 'Status iklan berhasil diubah.',
            'data' => $ad
        ]);
    }

    public function destroy($id)
    {
        $ad = Advertisement::findOrFail($id);
        
        if ($ad->image_path && Storage::disk('public')->exists($ad->image_path)) {
            Storage::disk('public')->delete($ad->image_path);
        }
        
        $ad->delete();

        return response()->json([
            'success' => true,
            'message' => 'Iklan berhasil dihapus.'
        ]);
    }

    /**
     * Parse placement input into array.
     * Accepts: JSON string '["beranda","webinar"]' or comma-separated 'beranda,webinar'
     */
    private function parsePlacement($input): array
    {
        if (is_array($input)) {
            return $input;
        }

        // Try JSON decode first
        $decoded = json_decode($input, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Fallback: comma-separated
        return array_map('trim', explode(',', $input));
    }
}
