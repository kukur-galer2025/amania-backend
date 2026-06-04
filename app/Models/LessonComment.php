<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonComment extends Model
{
    protected $fillable = ['user_id', 'course_lesson_id', 'parent_id', 'body'];

    public function user() { return $this->belongsTo(User::class); }
    public function lesson() { return $this->belongsTo(CourseLesson::class, 'course_lesson_id'); }
    public function parent() { return $this->belongsTo(LessonComment::class, 'parent_id'); }
    public function replies() { return $this->hasMany(LessonComment::class, 'parent_id'); }
}
