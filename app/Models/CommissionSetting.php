<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionSetting extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'creator_percent' => 'float',
        'active' => 'boolean',
    ];
}
