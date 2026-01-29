<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $guarded = ['id'];

    public function products(){
        return $this->belongsTo(Product::class);
    }

    public function creator() // name it 'creator' for clarity
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
