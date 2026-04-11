<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkdTryoutAnswer extends Model
{
    protected $table = 'skd_tryout_answers';
    protected $guarded = ['id'];

    protected $casts = [
        'is_doubtful' => 'boolean',
    ];

    // Relasi: Ini jawaban dari sesi ujian yang mana
    public function attempt()
    {
        return $this->belongsTo(SkdTryoutAttempt::class, 'skd_tryout_attempt_id');
    }

    // Relasi: Ini jawaban untuk soal yang mana
    public function question()
    {
        return $this->belongsTo(SkdQuestion::class, 'skd_question_id');
    }
}