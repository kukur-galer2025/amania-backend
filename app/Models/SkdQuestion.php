<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkdQuestion extends Model
{
    protected $table = 'skd_questions';
    protected $guarded = ['id'];

    // Relasi: Soal ini milik Tryout yang mana
    public function tryout()
    {
        return $this->belongsTo(SkdTryout::class, 'skd_tryout_id');
    }

    // Relasi: Soal ini masuk kategori materi apa
    public function subCategory()
    {
        return $this->belongsTo(SkdQuestionSubCategory::class, 'skd_question_sub_category_id');
    }

    // Relasi: Soal ini pernah dijawab apa aja sama user saat ujian
    public function answers()
    {
        return $this->hasMany(SkdTryoutAnswer::class, 'skd_question_id');
    }
}