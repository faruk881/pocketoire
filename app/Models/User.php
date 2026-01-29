<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
}
