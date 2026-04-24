<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EProductCategory extends Model
{
    protected $fillable = ['name', 'slug'];

    public function products()
    {
        return $this->hasMany(EProduct::class, 'e_product_category_id');
    }
}