<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Webhook;
use Stripe\Event;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Log the webhook hit
        // Log::info('Stripe webhook hit');

        // Retrieve the request's body and parse it as JSON
        $payload = $request->getContent();

        // Verify webhook signature
        $sigHeader = $request->header('Stripe-Signature');

        // Your webhook secret from Stripe dashboard
        $secret = config('services.stripe.webhook_secret');

        // Verify the event by constructing it with the Stripe library
        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $secret
            );
        } catch (\UnexpectedValueException $e) {
            return response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response('Invalid signature', 400);
        }

        // Handle event types
        switch ($event->type) {

            case 'account.updated':
                $this->handleAccountUpdated($event->data->object);
                break;

            // Add more later (transfer.paid, payout.failed, etc.)
        }

        // Return a 200 response to acknowledge receipt of the event
        return response('Webhook handled', 200);
    }

    // Handle account.updated event
    protected function handleAccountUpdated($account)
    {
        // Find the user by stripe_account_id
        $user = User::where('stripe_account_id', $account->id)->first();

        // Log the account update details
        Log::info('Stripe webhook: account.updated received', [
            'stripe_account_id' => $account->id,
            'payouts_enabled' => $account->payouts_enabled,
        ]);

        // If user not found, log a warning
        if (!$user) {
            Log::warning('Stripe webhook: user not found', [
                'stripe_account_id' => $account->id,
            ]);
            return;
        }

        // Update user's stripe_onboarded status
        $isOnboarded = $account->payouts_enabled === true;

        // Only update if the status has changed
        if ($user->stripe_onboarded !== $isOnboarded) {
            $user->update([
                'stripe_onboarded' => $isOnboarded,
            ]);
        }
    }
}
