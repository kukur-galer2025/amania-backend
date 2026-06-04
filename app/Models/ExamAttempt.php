<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamAttempt extends Model
{
    protected $fillable = ['user_id', 'course_exam_id', 'score', 'is_passed'];

    public function user() { return $this->belongsTo(User::class); }
    public function exam() { return $this->belongsTo(CourseExam::class, 'course_exam_id'); }
}
