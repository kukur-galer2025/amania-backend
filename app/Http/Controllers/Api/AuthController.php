<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\DB;        // Wajib untuk tabel password_reset_tokens
use Illuminate\Support\Str;              // Wajib untuk generate token unik
use Illuminate\Support\Facades\Mail;     // 🔥 WAJIB: Untuk mengirim email sungguhan

class AuthController extends Controller
{
    // ==========================================================
    // FUNGSI LOGIN (MASUK AKUN MANUAL)
    // ==========================================================
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah.'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'user' => $user,
                'token' => $token
            ]
        ]);
    }

    // ==========================================================
    // FUNGSI REGISTER (BUAT AKUN BARU MANUAL)
    // ==========================================================
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20', // 🔥 Validasi Nomor HP
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone, // 🔥 Simpan Nomor HP
            'password' => Hash::make($request->password),
            'role' => 'user',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil',
            'data' => [
                'user' => $user,
                'token' => $token
            ]
        ], 201); 
    }

    // ==========================================================
    // FUNGSI LOGIN / REGISTER LEWAT GOOGLE (API STATELESS)
    // ==========================================================
    public function googleLogin(Request $request)
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        try {
            $googleUser = Socialite::driver('google')->stateless()->userFromToken($request->token);
            $user = User::where('email', $googleUser->getEmail())->first();

            if (!$user) {
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'password' => null, 
                    'role' => 'user', 
                ]);
            } else {
                if (empty($user->google_id)) {
                    $user->update([
                        'google_id' => $googleUser->getId()
                    ]);
                }
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login Google berhasil',
                'data' => [
                    'user' => $user,
                    'token' => $token
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memverifikasi akun Google Anda. Token mungkin tidak valid atau kedaluwarsa.',
            ], 401);
        }
    }

    // ==========================================================
    // 🔥 FUNGSI BARU: KIRIM LINK RESET PASSWORD VIA EMAIL ASLI
    // ==========================================================
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ], [
            'email.exists' => 'Email tidak ditemukan di sistem kami.'
        ]);

        // 1. Generate Token Acak
        $token = Str::random(60);

        // 2. Simpan token ke database
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'email' => $request->email,
                'token' => Hash::make($token),
                'created_at' => now()
            ]
        );

        // 3. Buat URL mengarah ke domain amania.id yang diambil dari .env
        $frontendUrl = env('FRONTEND_URL', 'https://amania.id');
        $resetLink = $frontendUrl . "/reset-sandi?token=" . $token . "&email=" . urlencode($request->email);

        // 4. Kirim Email Sungguhan ke User
        try {
            Mail::raw(
                "Halo,\n\n" .
                "Kami menerima permintaan untuk mereset kata sandi akun EduTech Anda.\n\n" .
                "Silakan klik atau copy tautan berikut ke browser Anda untuk membuat kata sandi baru:\n\n" .
                $resetLink . "\n\n" .
                "Jika Anda tidak merasa meminta reset kata sandi, abaikan email ini.\n\n" .
                "Salam,\nTim EduTech Nusantara", 
                function ($message) use ($request) {
                    $message->to($request->email)
                            ->subject('Pemulihan Kata Sandi - EduTech Nusantara');
                }
            );

            return response()->json([
                'success' => true,
                'message' => 'Tautan pemulihan kata sandi telah dikirim ke email Anda. Silakan periksa kotak masuk atau folder spam.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim email. Pastikan konfigurasi SMTP di server sudah benar.',
            ], 500);
        }
    }

    // ==========================================================
    // 🔥 FUNGSI BARU: PROSES KATA SANDI BARU
    // ==========================================================
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // 1. Cek Token di Database
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        // 2. Validasi kesesuaian token
        if (!$resetRecord || !Hash::check($request->token, $resetRecord->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Tautan reset kata sandi tidak valid atau sudah kedaluwarsa.'
            ], 400);
        }

        // 3. Update Password User
        $user = User::where('email', $request->email)->first();
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        // 4. Hapus token agar tidak disalahgunakan dua kali
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kata sandi berhasil diperbarui! Silakan login dengan kata sandi baru Anda.'
        ]);
    }
}