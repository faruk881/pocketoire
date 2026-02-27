<?php

namespace App\Jobs;

use App\Models\Payout;
use App\Models\WalletTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Stripe\Transfer;
use Stripe\Payout as StripePayout;

class ProcessStripePayout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // The ID of the payout to process
    public int $payoutId;

    public function __construct(int $payoutId)
    {
        // Set the payout ID
        $this->payoutId = $payoutId;
    }

    public function handle(): void
    {
        Log::info('Stripe payout job started', ['payout_id' => $this->payoutId]);

        // Load payout with related wallet and user
        $payout  = Payout::with('wallet.user')->findOrFail($this->payoutId);
        $wallet  = $payout->wallet;
        $creator = $wallet->user;

        // Only process if payout is in 'approved' state
        if (in_array($payout->status, ['processing', 'completed'])) {
            Log::info('Payout already handled', ['payout_id' => $payout->id]);
            return;
        }

        // Ensure creator has a connected Stripe account
        if (!$creator->stripe_account_id) {
            $this->markPayoutFailed($payout, 'Creator has no connected Stripe account.');
            return;
        }

        // Set Stripe API key
        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            /**
             * 1️⃣ TRANSFER: Platform → Creator Stripe balance
             */
            if (!$payout->transfer_id) {
                // Include amount in the key to prevent mismatches if amount is edited
                $transferKey = 'transfer_' . $payout->id . '_' . (int)round($payout->amount * 100);

                $transfer = Transfer::create(
                    [
                        'amount'      => (int) round($payout->amount * 100),
                        'currency'    => strtolower($payout->currency),
                        'destination' => $creator->stripe_account_id,
                        'metadata'    => [
                            'payout_id' => $payout->id,
                            'user_id'   => $creator->id,
                        ],
                    ],
                    [
                        'idempotency_key' => $transferKey,
                    ]
                );

                $payout->update([
                    'transfer_id' => $transfer->id,
                    'status'      => 'funded',
                ]);

                Log::info('Stripe transfer created', [
                    'payout_id'   => $payout->id,
                    'transfer_id' => $transfer->id,
                ]);
            }

            /**
             * 2️⃣ PAYOUT: Creator Stripe → Creator bank
             */
            if (!$payout->stripe_payout_id) {
                $stripePayout = StripePayout::create(
                    [
                        'amount'   => (int) round($payout->amount * 100),
                        'currency' => strtolower($payout->currency),
                    ],
                    [
                        'stripe_account'  => $creator->stripe_account_id,
                        'idempotency_key' => 'creator_payout_' . $payout->id,
                    ]
                );

                $payout->update([
                    'stripe_payout_id' => $stripePayout->id,
                    'status'           => 'processing',
                ]);

                Log::info('Stripe payout created', [
                    'payout_id'        => $payout->id,
                    'stripe_payout_id' => $stripePayout->id,
                ]);
            }

        } catch (ApiErrorException $e) {
            // Log the Stripe error
            Log::error('Stripe error during payout', [
                'payout_id' => $payout->id,
                'type'      => get_class($e),
                'code'      => $e->getStripeCode(),
                'message'   => $e->getMessage(),
            ]);

            /**
             * Insufficient platform balance → refund wallet, do NOT retry
             */
            if ($e->getStripeCode() === 'balance_insufficient') {
                $this->markPayoutFailed(
                    $payout,
                    'Insufficient platform balance. Payout will retry later.'
                );
                return;
            }

            /**
             * Creator payout not enabled / bank issue → fail
             */
            if (in_array($e->getStripeCode(), [
                'payouts_not_enabled',
                'account_invalid',
                'bank_account_unverified',
            ])) {
                $this->markPayoutFailed(
                    $payout,
                    'Creator payout is not available. Please complete Stripe onboarding.'
                );
                return;
            }

            /**
             * Unknown Stripe error → retry job
             */
            throw $e;
        }
    }

    /**
     * Refund wallet + mark payout failed
     */
    protected function markPayoutFailed(Payout $payout, string $reason): void
    {
        DB::transaction(function () use ($payout, $reason) {

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
                    'payout_id' => $payout->id,
                    'reason'    => 'stripe_failure',
                ],
            ]);

            $wallet->increment('balance', $payout->amount);

            $payout->update([
                'status'         => 'failed',
                'failure_reason' => $reason,
            ]);
        });
    }
}
