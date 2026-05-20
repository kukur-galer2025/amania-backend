<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    public function courses()
    {
        return $this->hasMany(Course::class, 'course_category_id');
    }
}
