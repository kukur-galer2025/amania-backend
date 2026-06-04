<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseExam extends Model
{
    protected $fillable = ['course_id', 'title', 'passing_score', 'description'];

    public function course() { return $this->belongsTo(Course::class); }
    public function questions() { return $this->hasMany(ExamQuestion::class); }
    public function attempts() { return $this->hasMany(ExamAttempt::class); }
}
