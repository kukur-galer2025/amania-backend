<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;

class AdvertisementController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $query = Advertisement::where('is_active', true);

        if ($request->has('placement')) {
            $query->whereJsonContains('placement', $request->placement);
        }

        $ads = $query->orderBy('order', 'asc')
                     ->orderBy('created_at', 'desc')
                     ->get();
                            
        return response()->json([
            'success' => true,
            'data' => $ads
        ]);
    }
}
