<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $admin = $request->user();
        return response()->json([
            'success' => true,
            'unread_count' => $admin->unreadNotifications->count(),
            'notifications' => $admin->notifications()->take(15)->get()
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['success' => true]);
    }
}