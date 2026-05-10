<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EProductMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'e_product_id',
        'title',
        'type',
        'file_path',
        'link_url',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(EProduct::class, 'e_product_id');
    }
}