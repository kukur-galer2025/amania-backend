<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Models\Registration;
use App\Models\Article; 
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->get('q');
        $currentUser = $request->user();
        
        if (empty($query)) {
            return response()->json([
                'success' => true, 
                'users' => [], 
                'events' => [], 
                'registrations' => [],
                'articles' => []
            ], 200);
        }

        // 1. Cari Pengguna (Hanya Superadmin yang bisa cari User Global)
        $users = [];
        if ($currentUser->role === 'superadmin') {
            $users = User::select('id', 'name', 'email')
                ->where('name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->limit(3)->get()
                ->map(function ($user) {
                    $user->type = 'Peserta'; 
                    $user->link = "/admin/users?search=" . urlencode($user->name); 
                    return $user;
                });
        }

        // 2. Cari Event (Organizer cuma nemu event miliknya)
        $eventQuery = Event::select('id', 'title', 'slug')->where('title', 'like', "%{$query}%");
        if ($currentUser->role === 'organizer') {
            $eventQuery->where('user_id', $currentUser->id);
        }
        
        $events = $eventQuery->limit(3)->get()->map(function ($event) {
            $event->type = 'Event'; 
            $event->link = "/admin/events/edit?id={$event->id}"; 
            return $event;
        });

        // 3. Cari Transaksi/Tiket (Organizer cuma nemu pendaftar dari event miliknya)
        $regQuery = Registration::select('id', 'ticket_code', 'name', 'status', 'event_id')
            ->where(function($q) use ($query) {
                $q->where('ticket_code', 'like', "%{$query}%")
                  ->orWhere('name', 'like', "%{$query}%");
            });

        if ($currentUser->role === 'organizer') {
            $regQuery->whereHas('event', function ($q) use ($currentUser) {
                $q->where('user_id', $currentUser->id);
            });
        }

        $registrations = $regQuery->limit(3)->get()->map(function ($reg) {
            $reg->type = 'Transaksi'; 
            $reg->link = "/admin/registrations?search={$reg->ticket_code}"; 
            return $reg;
        });

        // 4. Cari Artikel (Hanya Superadmin yang bisa cari Artikel CMS)
        $articles = [];
        if ($currentUser->role === 'superadmin') {
            $articles = Article::select('id', 'title', 'slug')
                ->where('title', 'like', "%{$query}%")
                ->limit(3)->get()
                ->map(function ($article) {
                    $article->type = 'Artikel'; 
                    $article->link = "/admin/articles/edit?id={$article->id}"; 
                    return $article;
                });
        }

        return response()->json([
            'success' => true,
            'users' => $users,
            'events' => $events,
            'registrations' => $registrations,
            'articles' => $articles
        ], 200);
    }
}