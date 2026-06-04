<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamQuestion extends Model
{
    protected $fillable = ['course_exam_id', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option'];

    public function exam() { return $this->belongsTo(CourseExam::class, 'course_exam_id'); }
}
