<?php

namespace App\Http\Controllers;

use App\Http\Requests\StripeOnboardRequest;
use Illuminate\Http\Request;

class StripeConnectController extends Controller
{
    public function stripeOnboard(StripeOnboardRequest $request)
    {
        // Get the authenticated user
        $user = auth()->user();

        // Set Stripe API key
        try{
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
        } catch (\Exception $e) {
            return apiError('Failed to set Stripe API key: ' . $e->getMessage());
        }
        

        if(!$user->stripe_account_id) {
            // Create a new Stripe Express account
            try{
                $account = \Stripe\Account::create([
                    'type' => 'express',
                    'country' => $request->country,
                    'email' => $user->email,
                    'capabilities' => [
                        'card_payments' => ['requested' => true],
                        'transfers' => ['requested' => true],
                    ],
                ]);
            } catch (\Exception $e) {
                return apiError('Stripe account creation failed: ' . $e->getMessage());
            } 

            // Save the Stripe account ID to the user's record
            try {
                $user->stripe_account_id = $account->id;
                $user->save();
            } catch( \Exception $e) {
                return apiError('Failed to save Stripe account ID: ' . $e->getMessage());
            } 
        }  
        
        // Create an account link for onboarding
        try {
            $link = \Stripe\AccountLink::create([
                'account' => $user->stripe_account_id,
                'refresh_url' => url('/stripe/test/refresh'),
                'return_url'  => url('/stripe/test/return'),
                'type' => 'account_onboarding',
            ]);
        } catch (\Exception $e) {
            return apiError('Stripe account link creation failed: ' . $e->getMessage());
        }

        // Return the onboarding link URL
        return apiSuccess('Stripe onboarding link generated successfully.', ['url' => $link->url]);
    }
}
