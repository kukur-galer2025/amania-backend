<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkdTryoutAttempt extends Model
{
    protected $table = 'skd_tryout_attempts';
    protected $guarded = ['id'];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'is_passed' => 'boolean',
    ];

    // Relasi: Sesi Ujian ini dikerjakan oleh siapa
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relasi: Sesi Ujian ini lagi ngerjain Tryout yang mana
    public function tryout()
    {
        return $this->belongsTo(SkdTryout::class, 'skd_tryout_id');
    }

    // Relasi: Rekapan jawaban user selama ujian ini
    public function answers()
    {
        return $this->hasMany(SkdTryoutAnswer::class, 'skd_tryout_attempt_id');
    }
}