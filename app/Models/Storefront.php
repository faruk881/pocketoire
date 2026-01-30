<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Storefront extends Model
{
    protected $guarded = ['id'];

    // The wallet will automatically be created when a user is created
    protected static function booted()
    {
        static::created(function (Storefront $storefront) {
            $user = $storefront->user;

            if (!$user->wallet) {
                $user->wallet()->create([
                    'balance' => 0.00,
                    'currency' => 'USD',
                    'status' => 'active',
                ]);
            }
        });
    }

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
