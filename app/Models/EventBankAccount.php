<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventBankAccount extends Model
{
    // 🔥 PERBAIKAN: Hapus huruf 's', gunakan event_id sesuai database
    protected $fillable = ['event_id', 'bank_code', 'account_number', 'account_holder'];

    public function event()
    {
        // 🔥 PERBAIKAN: Foreign Key adalah 'event_id'
        return $this->belongsTo(Event::class, 'event_id');
    }
}