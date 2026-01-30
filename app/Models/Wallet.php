<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $guarded = ['id'];

    /**
    * Wallet belongs to a user (creator)
    */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Wallet has many transactions
     */
    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Completed transactions only (useful scope)
     */
    public function completedTransactions()
    {
        return $this->transactions()->where('status', 'completed');
    }
}
