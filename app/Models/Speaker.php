<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Speaker extends Model
{
    protected $fillable = ['event_id', 'name', 'role', 'photo'];
    public function event() { return $this->belongsTo(Event::class); }
}