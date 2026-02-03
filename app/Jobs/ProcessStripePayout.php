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
use Stripe\Exception\InvalidRequestException;
use Stripe\Stripe;
use Stripe\Transfer;

class ProcessStripePayout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $payoutId;

    public function __construct(int $payoutId)
    {
        $this->payoutId = $payoutId;
    }

    public function handle(): void
    {
        Log::info("STARTED");
        $payout = Payout::with('wallet.user')->findOrFail($this->payoutId);
        $wallet = $payout->wallet;
        $creator = $wallet->user;

        // Guard: already processed
        if (in_array($payout->status, ['sent', 'completed'])) {
            Log::info('Payout already processed', ['payout_id' => $payout->id]);
            return;
        }

        // Guard: missing Stripe account
        if (!$creator->stripe_account_id) {
            $this->markPayoutFailed(
                $payout,
                'Creator has no connected Stripe account.'
            );
            return;
        }

        Stripe::setApiKey(config('services.stripe.secret'));
        $balance = \Stripe\Balance::retrieve();

        Log::info('Stripe balance', $balance->toArray());
        Log::info('Payout currency', ['currency' => $payout->currency]);

        try {
            /**
             * 1️⃣ Create Stripe transfer (NO DB transaction here)
             */
            $transfer = Transfer::create(
                [
                    'amount'      => (int) round($payout->amount),
                    'currency'    => strtolower($payout->currency),
                    'destination' => $creator->stripe_account_id,
                    'metadata'    => [
                        'payout_id' => $payout->id,
                        'user_id'   => $creator->id,
                    ],
                ],
                [
                    // Prevent duplicate payouts
                    'idempotency_key' => 'payout_' . $payout->id,
                ]
            );

            /**
             * 2️⃣ Update DB state atomically
             */
            DB::transaction(function () use ($payout, $transfer) {
                $payout->update([
                    'status'              => 'processing', 
                    'external_reference'  => $transfer->id,
                ]);
            });

            Log::info('Stripe transfer created successfully', [
                'payout_id'   => $payout->id,
                'transfer_id' => $transfer->id,
            ]);

        } catch (InvalidRequestException $e) {

            // Insufficient Stripe balance → refund wallet
            if ($e->getStripeCode() === 'balance_insufficient') {

                Log::warning('Stripe payout failed: insufficient funds', [
                    'payout_id'  => $payout->id,
                    'stripe_msg' => $e->getMessage(),
                ]);

                $this->markPayoutFailed(
                    $payout,
                    'Insufficient platform balance. Payout will be retried later.'
                );

                // Do NOT retry
                return;
            }

            // Other Stripe errors → retry job
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
                'wallet_id'       => $wallet->id,
                'type'            => 'credit',
                'source'          => 'payout_failed',
                'amount'          => $payout->amount,
                'balance_before'  => $wallet->balance,
                'balance_after'   => $wallet->balance + $payout->amount,
                'status'          => 'completed',
                'metadata'        => [
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
