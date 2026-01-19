<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\Customer;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function createCustomer($user): Customer
    {
        return Customer::create([
            'name'  => $user->name,
            'email' => $user->email,
            'metadata' => [
                'user_id' => $user->id,
            ],
        ]);
    }
}
