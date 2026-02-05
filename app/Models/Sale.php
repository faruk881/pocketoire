<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_commissioned' => 'boolean',
    ];

    public function product(){
        return $this->belongsTo(Product::class);
    }

    public function user() // name it 'creator' for clarity
    {
        return $this->belongsTo(User::class);
    }
    
    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
