<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Mengambil 10 notifikasi terbaru user
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'unread_count' => $user->unreadNotifications->count(),
            'notifications' => $user->notifications()->take(10)->get()
        ]);
    }

    /**
     * Menandai semua notifikasi user sebagai 'sudah dibaca'
     */
    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        
        return response()->json([
            'success' => true,
            'message' => 'Semua notifikasi telah dibaca'
        ]);
    }
}