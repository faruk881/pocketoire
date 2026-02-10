<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];
    // protected $fillable = [
    //     'name',
    //     'email',
    //     'email_verified_at',
    //     'password',
    //     'profile_photo',
    //     'cover_photo',
    //     'role',
    //     'account_type',
    //     'status',
    //     'status_reason',
    //     'stripe_customer_id',
    //     'stripe_account_id',
    //     'stripe_onboarded',
    //     'moderated_by',
    //     'moderated_at'
    // ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password_reset_expires_at' => 'datetime',
            'otp_expires_at'    => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function storefront(){
        return $this->hasOne(Storefront::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function wallet() {
        return $this->hasOne(Wallet::class);
    }

    public function payouts()
    {
        return $this->hasMany(Payout::class);
    }

    public function creator_comission_override()
    {
        return $this->hasOne(CreatorCommissionOverrides::class);
    }

    public function isCreator()
    {
        return $this->account_type === 'creator';
    }

    public function isBuyer()
    {
        return $this->account_type === 'buyer';
    }

    public function savedProducts()
    {
        return $this->belongsToMany(Product::class, 'saved_products')
                    ->withTimestamps();
    }

    // Get the commission ratio for the creator 
    //commission_percent in the end it will like this "->each->append('commission_percent');"
    public function getCommissionPercentAttribute()
    {
        $now = now();

        $override = CreatorCommissionOverrides::where('user_id', $this->id)
            ->where('effective_from', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('effective_to')
                ->orWhere('effective_to', '>=', $now);
            })
            ->first();

        if ($override) {
            return $override->creator_commission_percent;
        }

        return CommissionSetting::where('active', true)
            ->latest()
            ->value('global_creator_commission_percent') ?? 0;
    }

    // public function recentViews() {
    //     return $this->belongsToMany(Product::class, 'recent_views')
    //                 ->withPivot('viewed_at')
    //                 ->orderByPivot('viewed_at', 'desc')
    //                 ->take(4); // Strictly limits to 4
    // }



    // public function commission_percent()
    // {
    //     $now = now();

    //     $override = CreatorCommissionOverrides::where('user_id', $this->id)
    //         ->where('effective_from', '<=', $now)
    //         ->where(function ($q) use ($now) {
    //             $q->whereNull('effective_to')
    //             ->orWhere('effective_to', '>=', $now);
    //         })
    //         ->first();

    //     if ($override) {
    //         return $override->creator_commission_percent;
    //     }

    //     return CommissionSetting::where('active', true)
    //         ->latest()
    //         ->value('global_creator_commission_percent') ?? 0;
    // }
}
