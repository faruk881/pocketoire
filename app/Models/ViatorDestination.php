<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ViatorDestination extends Model
{
    use HasFactory;

    protected $fillable = [
        'destination_id',
        'name',
        'type',
        'default_currency_code',
        'time_zone',
    ];
}
