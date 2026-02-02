<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreatorEarningController extends Controller
{
    public function getCreatorEarnings()
    {
        try {
            // Get the authenticated creator's ID
            $creatorId = auth()->id();

            // Fetch sales data for the creator
            $sales = Sale::query()
                ->where('sales.user_id', $creatorId)
                ->whereIn('sales.status', ['confirmed', 'amended']) 
                ->leftJoin('products', 'sales.product_id', '=', 'products.id')
                ->leftJoin('product_images', 'products.id', '=', 'product_images.product_id')
                ->selectRaw('
                    sales.product_code,
                    products.id as product_id,
                    products.title,
                    MIN(product_images.image) as main_image,
                    COUNT(sales.id) as total_conversions,
                    SUM(sales.creator_commission) as total_earnings
                ')
                ->groupBy(
                    'sales.product_code',
                    'products.id',
                    'products.title'
                )
                ->get();

            // Prepare the response data
            $products = $sales->map(fn ($row) => [
                    'id' => $row->product_id,               // NULL if product missing
                    'product_code' => $row->product_code,   // ALWAYS available
                    'title' => $row->title ?? 'Unlisted Product',
                    'main_image' => $row->main_image,
                    'total_conversions' => (int) $row->total_conversions,
                    'total_clicks' => 0, // clicks impossible without product
                    'total_earnings' => (float) $row->total_earnings,
                ]);

            $wallet = Wallet::where('user_id', $creatorId)
            ->select('balance','currency','status')                
            ->first();

            $payouts = Payout::where('user_id', $creatorId)->select('id','user_id','wallet_id','amount','currency','method','status','created_at')->get();

            $last_payout = $payouts->sortByDesc('created_at')->first();

            $data = [
                'products' => $products,
                'wallet'   => $wallet,
                'payouts'  => $payouts,
                'last_payout' => $last_payout,
            ];
            return apiSuccess('All data loaded.',$data);
        } catch (\Exception $e) {
            return apiError('An error occurred: ' . $e->getMessage());
        }
    }

    public function storePayoutRequest(Request $request)
    {
        // Validate input
        $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        // Minimum payout amount
        $minAmount = 50; // Minimum payout amount in USD

        // Get user and wallet
        $user   = auth()->user();
        $wallet = $user->wallet;

        // Validation checks
        if (!$wallet || $wallet->status !== 'active') {
            return apiError('Wallet is not active', 403);
        }

        // Check if Stripe account is onboarded
        if (!$user->stripe_account_id || !$user->stripe_onboarded) {
            return apiError('Stripe account not onboarded', 409);
        }

        // Check sufficient balance
        if ($request->amount > $wallet->balance) {
            return apiError('Insufficient wallet balance', 422);
        }

        // Check minimum payout amount
        if( $request->amount < $minAmount ) {
            return apiError('Minimum payout amount is '.$minAmount.' USD', 422);
        }

        // Start transaction
        DB::beginTransaction();

        try {
            // Create payout request
            $payout = Payout::create([
                'user_id'      => $user->id,
                'wallet_id'    => $wallet->id,
                'amount'       => $request->amount,
                'status'       => 'requested',
                'requested_at' => now(),
            ]);

            // Lock funds (debit wallet)
            WalletTransaction::create([
                'wallet_id'       => $wallet->id,
                'sale_id'         => null,
                'type'            => 'debit',
                'source'          => 'payout_request',
                'amount'          => $request->amount,
                'balance_before'  => $wallet->balance,
                'balance_after'   => $wallet->balance - $request->amount,
                'status'          => 'completed',
                'metadata'        => [
                    'payout_id' => $payout->id,
                ],
            ]);

            // Update wallet balance
            $wallet->decrement('balance', $request->amount);

            DB::commit();

            return apiSuccess(
                'Payout request submitted successfully.',
                [
                    'payout_id' => $payout->id,
                    'amount'    => $payout->amount,
                    'status'    => $payout->status,
                ],
                201
            );

        } catch (\Throwable $e) {
            DB::rollBack();
            return apiError('Failed to create payout request '.$e->getMessage(), 500);
        }
    }   
}
