<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Storefront extends Model
{
    protected $guarded = ['id'];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function albums() {
        return $this->hasMany(Album::class);
    }

    public function products() {
        return $this->hasMany(Product::class);
    }
}
