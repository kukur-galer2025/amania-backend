<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * TAMPILKAN SEMUA USER (BISA FILTER ROLE)
     */
    public function index(Request $request)
    {
        $query = User::query();
        
        // Filter jika Superadmin hanya ingin melihat daftar tertentu
        if ($request->has('role') && $request->role != 'all') {
            $query->where('role', $request->role);
        }

        $users = $query->latest()->get();
        return response()->json(['success' => true, 'data' => $users]);
    }

    /**
     * 🔥 SUPERADMIN MEMBUAT AKUN BARU (ORGANIZER/USER DENGAN AVATAR) 🔥
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:superadmin,organizer,user',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120' // Maksimal 5MB
        ]);

        $data = $request->only(['name', 'email', 'role']);
        $data['password'] = Hash::make($request->password);

        // Proses Upload Avatar jika ada
        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user = User::create($data);

        return response()->json([
            'success' => true,
            'message' => "Akun {$user->role} berhasil dibuat!",
            'data' => $user
        ], 201);
    }

    /**
     * 🔥 SUPERADMIN MENGEDIT DATA USER & AVATAR 🔥
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            // Email harus unik, tapi abaikan jika itu email milik user ini sendiri
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|in:superadmin,organizer,user',
            'password' => 'nullable|string|min:6', // Opsional, hanya jika ingin ganti password
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120' // Maks 5MB
        ]);

        $data = $request->only(['name', 'email', 'role']);

        // Jika password diisi, maka update passwordnya
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        // Proses Upload Avatar jika ada file baru
        if ($request->hasFile('avatar')) {
            // Hapus avatar lama dari storage jika ada
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => "Data pengguna berhasil diperbarui!",
            'data' => $user
        ]);
    }

    /**
     * SUPERADMIN MERESET PASSWORD USER
     */
    public function resetPassword(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|string|min:6'
        ]);

        $user = User::findOrFail($id);
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'success' => true, 
            'message' => 'Password berhasil direset'
        ]);
    }

    /**
     * SUPERADMIN MENGHAPUS USER
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Mencegah superadmin menghapus akunnya sendiri
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false, 
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri.'
            ], 400);
        }

        // Hapus avatar fisik jika ada
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->delete();

        return response()->json([
            'success' => true, 
            'message' => 'User berhasil dihapus'
        ]);
    }
}