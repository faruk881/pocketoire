<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = ['id'];

    public function user() {
        return $this->belongsTo(User::class);
    }


    public function productImages() {
        return $this->hasMany(ProductImage::class);
    }

    public function albums() {
        return $this->belongsTo(Album::class);
    }

    public function storefront() {
        return $this->belongsTo(Storefront::class);
    }
}
