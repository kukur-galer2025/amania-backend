<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkdUserTryout extends Model
{
    protected $table = 'skd_user_tryouts';
    protected $guarded = ['id'];

    protected $casts = [
        'expired_at' => 'datetime',
    ];

    // Relasi: Hak akses ini punya siapa
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relasi: Hak akses ini untuk Tryout yang mana
    public function tryout()
    {
        return $this->belongsTo(SkdTryout::class, 'skd_tryout_id');
    }
}