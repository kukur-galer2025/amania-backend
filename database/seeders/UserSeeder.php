<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Superadmin (Bisa akses dan kelola semuanya)
        User::create([
            'name' => 'Super Administrator',
            'email' => 'admin@amania.id',
            'password' => Hash::make('password'),
            'role' => 'superadmin', // 🔥 Sudah disesuaikan
            'bio' => 'Platform Super Administrator',
        ]);

        // 2. Akun Organizer (Hanya bisa kelola event miliknya sendiri)
        User::create([
            'name' => 'BEM Unsoed (Organizer)',
            'email' => 'organizer@amania.id',
            'password' => Hash::make('password'),
            'role' => 'organizer', // 🔥 Akun tes untuk fitur Multi-Tenant
            'bio' => 'Event Organizer Resmi Universitas Jenderal Soedirman.',
        ]);

        // 3. Akun Member/Peserta biasa
        User::create([
            'name' => 'Prima Dzaky',
            'email' => 'prima@amania.id',
            'password' => Hash::make('password'),
            'role' => 'user',
            'bio' => 'Mahasiswa Informatika yang antusias belajar teknologi.',
        ]);
    }
}