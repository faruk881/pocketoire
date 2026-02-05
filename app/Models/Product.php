<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = ['id'];

    public function user() {
        return $this->belongsTo(User::class);
    }


    public function first_image()
    {
        // Fetches the single oldest image (good for a main thumbnail)
        return $this->hasOne(ProductImage::class)->oldestOfMany();
    }
    
    public function product_images() {
        return $this->hasMany(ProductImage::class);
    }

    public function album() {
        return $this->belongsTo(Album::class);
    }

    public function storefront() {
        return $this->belongsTo(Storefront::class);
    }

    public function clicks()
    {
        return $this->hasMany(ProductClick::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}
