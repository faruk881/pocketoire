<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use Illuminate\Http\Request;
use Stripe\Webhook;
use Stripe\Event;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
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
            case 'transfer.paid':
                $this->handleTransferPaid($event->data->object);
                break;

            case 'transfer.failed':
                $this->handleTransferFailed($event->data->object);
                break;

            case 'transfer.reversed':
                $this->handleTransferReversed($event->data->object);
                break;

            default:
                Log::info('Unhandled Stripe event', [
                    'type' => $event->type
                ]);

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

    protected function handleTransferPaid($transfer)
    {
        $payout = Payout::where('stripe_transfer_id', $transfer->id)->first();

        if (!$payout || $payout->status === 'paid') {
            return;
        }

        DB::transaction(function () use ($payout, $transfer) {
            $payout->update([
                'status'  => 'paid',
                'paid_at'=> now(),
            ]);

            WalletTransaction::create([
                'wallet_id'      => $payout->wallet_id,
                'type'           => 'debit',
                'source'         => 'sale_commission',
                'amount'         => $payout->amount,
                'balance_before' => $payout->balance_before,
                'balance_after'  => $payout->balance_after,
                'status'         => 'completed',
                'metadata'       => [
                    'stripe_transfer_id' => $transfer->id,
                ],
            ]);
        });
    }

    protected function handleTransferFailed($transfer)
    {
        $payout = Payout::where('stripe_transfer_id', $transfer->id)->first();
        if (!$payout || $payout->status === 'failed') return;

        DB::transaction(function () use ($payout) {
            $wallet = $payout->wallet;

            $wallet->increment('balance', $payout->amount);

            WalletTransaction::create([
                'wallet_id'      => $wallet->id,
                'type'           => 'credit',
                'source'         => 'refund',
                'amount'         => $payout->amount,
                'balance_before' => $wallet->balance - $payout->amount,
                'balance_after'  => $wallet->balance,
                'status'         => 'completed',
                'metadata'       => [
                    'reason' => 'stripe_transfer_failed',
                ],
            ]);

            $payout->update([
                'status' => 'failed',
            ]);
        });
    }
    
    protected function handleTransferReversed($transfer)
    {
        $payout = Payout::where('stripe_transfer_id', $transfer->id)->first();
        if (!$payout) return;

        DB::transaction(function () use ($payout) {
            $wallet = $payout->wallet;

            $wallet->increment('balance', $payout->amount);

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type'      => 'credit',
                'source'    => 'refund',
                'amount'    => $payout->amount,
                'status'    => 'completed',
                'metadata'  => [
                    'reason' => 'stripe_transfer_reversed',
                ],
            ]);

            $payout->update([
                'status' => 'cancelled',
            ]);
        });
    }
}
