<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Album extends Model
{
    protected $guarded = ['id'];

    public function storefront() {
        return $this->belongsTo(Storefront::class);
    }
    public function products() {
        return $this->hasMany(Product::class);
    }
}
