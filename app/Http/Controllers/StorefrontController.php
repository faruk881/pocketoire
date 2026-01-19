<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateStorefrontRequest;
use App\Http\Requests\StorefrontUrlCheckRequest;
use App\Models\Storefront;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Auth\Events\Validated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\SetupIntent;

class StorefrontController extends Controller
{
    public function createStorefront(CreateStorefrontRequest $request, StripeService $stripeService) {

        // Get current user.
        $user = auth()->user();

        // One storefront per user
        if ($user->storefront) {
            return apiError('You already have a storefront.', 403);
        }

        // Fetch Data
        $data = $request->validated();

        // null clientSected variable
        $clientSecret = null;

        // -----------------------------
        // STRIPE (outside transaction)
        // Create and save stripe customer id
        // -----------------------------
        if (! $user->stripe_customer_id) {
            $stripeCustomer = $stripeService->createCustomer($user);

            $user->stripe_customer_id = $stripeCustomer->id;
            $user->save();
        }

        // Load stripe secrets
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        // Create setup intent for frontend to save card.
        $setupIntent = SetupIntent::create([
            'customer' => $user->stripe_customer_id,
            'payment_method_types' => ['card'],
        ]);

        // Put the secrets to previously created empty variable
        $clientSecret = $setupIntent->client_secret;

        // -----------------------------
        // DATABASE TRANSACTION
        // -----------------------------

        DB::beginTransaction();

        try {
            // Create storefront
            $storefront = Storefront::create([
                'user_id' => $user->id,
                'name'    => $data['storename'],
                'slug'    => Str::slug($data['storeurl']),
                'status'  => 'pending',
                'bio'     => $data['description'] ?? null,
            ]);

            // Update user profile assets
            $userData = [];

            if ($request->hasFile('profile_photo')) {
                $userData['profile_photo'] = $request
                    ->file('profile_photo')
                    ->store('users/profile', 'public');
            }

            if ($request->hasFile('cover_photo')) {
                $userData['cover_photo'] = $request
                    ->file('cover_photo')
                    ->store('users/cover', 'public');
            }

            // Change account type to creator
            $userData['account_type'] = 'creator';

            if (! empty($userData)) {
                $user->update($userData);
            }

            DB::commit();
            // End of transition.

            return apiSuccess(
                'Storefront created successfully. Waiting for approval.',
                [
                    'storefront' => $storefront,
                    'user' => $user->only(['profile_photo', 'cover_photo', 'stripe_customer_id']),
                    'client_secret' => $clientSecret
                ],
                201
            );

        } catch (\Throwable $e) {
            DB::rollBack();

            return apiError(
                'Failed to create storefront.',
                500,
                ['exception' => $e->getMessage()]
            );
        }
    } // End of createStorefront

    public function storefrontUrlCheck(StorefrontUrlCheckRequest $request) {
        $data = $request->validated();
        $slug = Str::slug($data['storeurl']);
        $slug_is_exist = Storefront::where('slug',$slug)->first();
        if($slug_is_exist) {
            return apiError('The url already taken');
        } else {
            return apiSuccess('You can use this url');
        }


    }


}
