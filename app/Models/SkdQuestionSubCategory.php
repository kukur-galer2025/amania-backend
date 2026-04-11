<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkdQuestionSubCategory extends Model
{
    protected $table = 'skd_question_sub_categories';
    protected $guarded = ['id'];

    // Relasi: Sub-kategori ini dipakai di soal mana aja
    public function questions()
    {
        return $this->hasMany(SkdQuestion::class, 'skd_question_sub_category_id');
    }
}