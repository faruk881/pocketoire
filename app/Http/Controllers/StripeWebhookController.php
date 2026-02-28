<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('Stripe webhook received', ['headers' => $request->headers->all(), 'body' => $request->getContent()]);
        // Retrieve the request's body and parse it as JSON
        $payload    = $request->getContent();
        $sigHeader  = $request->header('Stripe-Signature');
        $secret     = config('services.stripe.webhook_secret');

        try {
            // Verify the webhook signature and construct the event
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

        // Handle the event
        switch ($event->type) {
            case 'account.updated':
                $this->handleAccountUpdated($event->data->object);
                break;

            case 'payout.paid':
                $this->handlePayoutPaid($event->data->object);
                break;

            case 'payout.failed':
                $this->handlePayoutFailed($event->data->object);
                break;

            case 'payout.canceled':
                $this->handlePayoutCanceled($event->data->object);
                break;

            default:
                Log::info('Unhandled Stripe event', [
                    'type' => $event->type
                ]);
        }

        // Return a 200 response
        return response('Webhook handled', 200);
    }

    /**
     * Creator onboarding / payout availability
     */
    protected function handleAccountUpdated($account)
    {
        Log::info('Stripe account.updated received', ['account_id' => $account->id]);
        // Find the user by Stripe account ID
        $user = User::where('stripe_account_id', $account->id)->first();

        if (!$user) {
            Log::warning('Stripe account.updated: user not found', [
                'stripe_account_id' => $account->id,
            ]);
            return;
        }

        $user->update([
            'stripe_onboarded' => (bool) $account->payouts_enabled,
        ]);
    }

    /**
     * ✅ Payout success (money reached bank)
     */
    protected function handlePayoutPaid($stripePayout)
    {
        Log::info('Stripe payout.paid received', ['payout_id' => $stripePayout->id]);
        $payout = Payout::where('stripe_payout_id', $stripePayout->id)->first();

        if (!$payout || $payout->status === 'completed') {
            return;
        }

        DB::transaction(function () use ($payout, $stripePayout) {

            $payout->update([
                'status'   => 'paid',
                'paid_at'  => now(),
            ]);
        });
    }

    /**
     * ❌ Bank payout failed → refund wallet
     */
    protected function handlePayoutFailed($stripePayout)
    {
        log::info('Stripe payout.failed received', ['payout_id' => $stripePayout->id]);
        $payout = Payout::where('stripe_payout_id', $stripePayout->id)->first();
        if (!$payout || $payout->status === 'failed') return;

        DB::transaction(function () use ($payout) {

            $wallet = $payout->wallet;
            WalletTransaction::create([
                'wallet_id'      => $wallet->id,
                'type'           => 'credit',
                'source'         => 'payout_failed',
                'amount'         => $payout->amount,
                'balance_before' => $wallet->balance,
                'balance_after'  => $wallet->balance + $payout->amount,
                'status'         => 'completed',
                'metadata'       => [
                    'reason' => 'stripe_payout_failed',
                ],
            ]);
            $wallet->increment('balance', $payout->amount);

            $payout->update([
                'status' => 'failed',
            ]);
        });
    }

    /**
     * ❌ Payout canceled → refund wallet
     */
    protected function handlePayoutCanceled($stripePayout)
    {
        log::info('Stripe payout.canceled received', ['payout_id' => $stripePayout->id]);
        $payout = Payout::where('stripe_payout_id', $stripePayout->id)->first();
        if (!$payout) return;

        DB::transaction(function () use ($payout) {

            $wallet = $payout->wallet;

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type'      => 'credit',
                'source'    => 'payout_canceled',
                'amount'    => $payout->amount,
                'balance_before' => $wallet->balance,
                'balance_after'  => $wallet->balance + $payout->amount,
                'status'    => 'completed',
                'metadata'       => [
                    'reason' => 'Payout cancelled',
                ],
            ]);
            $wallet->increment('balance', $payout->amount);

            $payout->update([
                'status' => 'cancelled',
            ]);
        });
    }
}
