<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens; 
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',     // 🔥 Kolom Nomor HP ditambahkan
        'password',
        'role',      // superadmin, organizer, user
        'avatar',    // Kolom foto
        'bio',       // Kolom bio
        'google_id', // Mengizinkan penyimpanan ID Google
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relasi: User (Peserta) memiliki banyak riwayat pendaftaran
    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    // 🔥 TAMBAHAN RELASI: User (Organizer) memiliki banyak Event 🔥
    public function events()
    {
        return $this->hasMany(Event::class, 'user_id');
    }

    public function articles()
    {
        return $this->hasMany(Article::class, 'user_id');
    }

    // 🔥 TAMBAHAN RELASI: User (Pembeli) memiliki banyak riwayat pembelian E-Produk
    public function eProductPurchases()
    {
        return $this->hasMany(EProductPurchase::class, 'user_id');
    }

    public function eProducts()
    {
        return $this->hasMany(EProduct::class, 'user_id');
    }
}