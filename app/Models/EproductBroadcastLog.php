<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EproductBroadcastLog extends Model
{
    protected $guarded = [];

    public function product()
    {
        return $this->belongsTo(EProduct::class, 'e_product_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
