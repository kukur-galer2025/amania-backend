<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseLesson extends Model
{
    protected $fillable = [
        'course_section_id',
        'title',
        'type',
        'youtube_url',
        'text_content',
        'file_path',
        'file_name',
        'duration_minutes',
        'is_preview',
        'order',
    ];

    protected $casts = [
        'is_preview' => 'boolean',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }
}
