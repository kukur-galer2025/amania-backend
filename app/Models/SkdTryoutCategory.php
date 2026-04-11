<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SkdTryoutCategory extends Model
{
    use HasFactory;

    protected $table = 'skd_tryout_categories';

    protected $fillable = [
        'name',
        'slug',
    ];

    // Relasi: 1 Kategori punya banyak Tryout
    public function tryouts()
    {
        return $this->hasMany(SkdTryout::class, 'skd_tryout_category_id');
    }
}