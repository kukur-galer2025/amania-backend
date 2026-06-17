<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiMentorChat extends Model
{
    protected $fillable = ['user_id', 'lesson_id', 'role', 'content'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lesson()
    {
        return $this->belongsTo(CourseLesson::class, 'lesson_id');
    }
}
