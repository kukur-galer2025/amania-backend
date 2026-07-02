<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Withdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'status',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'notes',
        'transfer_proof'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
