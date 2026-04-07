<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    protected $fillable = [
        'ticket_code', 
        'user_id', 
        'event_id', 
        'name', 
        'email', 
        'payment_proof', 
        'status', 
        'rejection_reason', // Ditambahkan agar bisa di-update oleh Controller
        'tier', 
        'total_amount'
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function event() { return $this->belongsTo(Event::class); }
}