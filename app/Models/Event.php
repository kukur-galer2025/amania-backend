<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 
        'slug',
        'description', 
        'venue', 
        'start_time', 
        'end_time', 
        'quota', 
        'basic_price',
        'premium_price', 
        'certificate_link', 
        'certificate_tier',
        'image',
        'user_id' // 🔥 DITAMBAHKAN: Agar ID Organizer bisa disimpan saat create event
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'basic_price' => 'integer',
        'premium_price' => 'integer',
    ];

    /**
     * Relasi ke Organizer (Pembuat Event)
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function speakers(): HasMany
    {
        return $this->hasMany(Speaker::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(EventBankAccount::class, 'event_id');
    }
}