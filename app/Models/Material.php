<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 
        'title', 
        'type', 
        'access_tier', // 🔥 Kolom baru ditambahkan
        'file_path', 
        'link'
    ];

    public function event() 
    { 
        return $this->belongsTo(Event::class); 
    }
}