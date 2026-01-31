<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreatorCommissionOverrides extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'creator_percent' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
