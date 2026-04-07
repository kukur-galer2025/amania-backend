<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $roles (Bisa menerima multiple roles dipisahkan dengan '|')
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        // 1. Pastikan user sudah login (cek object user)
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi berakhir. Silakan login ke Amania terlebih dahulu.'
            ], 401);
        }

        // 2. Pecah string roles menjadi array (Bisa membaca 'superadmin|organizer')
        $roleArray = explode('|', $roles);

        // 3. Cek apakah role user ada di dalam daftar yang diizinkan
        if (!in_array($user->role, $roleArray)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses Ditolak. Anda tidak memiliki izin untuk halaman atau fitur ini.'
            ], 403);
        }

        return $next($request);
    }
}