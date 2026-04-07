<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        // Ambil data user yang sedang login dari token Sanctum
        $user = $request->user();

        // Validasi input
        $request->validate([
            'name' => 'required|string|max:255',
            // Pengecualian unik email untuk user itu sendiri
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id, 
            'password' => 'nullable|string|min:8|confirmed',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120', // <-- UBAH KE 5MB (5120 KB)
            'bio' => 'nullable|string|max:1000', // <-- TAMBAHAN: Validasi untuk Bio
        ]);

        // Update nama, email, dan bio
        $user->name = $request->name;
        $user->email = $request->email;
        $user->bio = $request->bio; // <-- TAMBAHAN: Simpan data bio

        // Jika user mengisi password baru, update passwordnya
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        // Jika user mengunggah foto profil baru
        if ($request->hasFile('avatar')) {
            // Hapus foto lama jika ada
            if ($user->avatar && !str_starts_with($user->avatar, 'http') && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            
            // Simpan foto baru ke folder storage/app/public/avatars
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui!',
            'data' => $user
        ]);
    }
}