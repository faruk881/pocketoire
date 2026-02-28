<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreatorEarningController extends Controller
{
    public function getCreatorEarnings()
    {
        try {
            // Get the authenticated creator's ID
            $creatorId = auth()->id();

            // Calculate pending payouts
            $pendingPayoutsAmounts = Payout::where('user_id', $creatorId)
                ->whereIn('status', ['requested', 'processing'])
                ->sum('amount');
        
            // Total Paid Amounts
            $totalPaidAmounts = Payout::where('user_id', $creatorId)
                ->where('status', 'paid')
                ->sum('amount');

            // Monthly Paid Amounts
            $totalPaidThisMonth = Payout::where('user_id', $creatorId)
                ->where('status', 'paid')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount');

            // Previous Months Paid Amounts
            $totalPaidLastMonth = Payout::where('user_id', $creatorId)
                ->where('status', 'paid')
                ->where(function ($query) {
                    $query->whereMonth('created_at', '<', now()->month)
                          ->orWhereYear('created_at', '<', now()->year);
                })
                ->sum('amount');

            // Calculate Percentage Change
            $percentageChange = 0;
            
            // Avoid division by zero
            if ($totalPaidLastMonth > 0) {
                $percentageChange = (($totalPaidThisMonth - $totalPaidLastMonth) / $totalPaidLastMonth) * 100;
            } elseif ($totalPaidThisMonth > 0) {
                // If last month was 0 but this month is > 0, it's a 100% increase
                $percentageChange = 100;
            }

            // Format to 2 decimal places
            $percentageChange = round($percentageChange, 2);

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
            // Fetch wallet and payouts
            $wallet = Wallet::where('user_id', $creatorId)
            ->select('balance','currency','status')                
            ->first();

            // Fetch payouts
            $payouts = Payout::where('user_id', $creatorId)->select('id','user_id','wallet_id','amount','currency','method','status','created_at')->orderBy('created_at', 'desc')->get();

            // Fetch last payout
            $last_payout = $payouts->sortByDesc('created_at')->first();

            // Prepare the response data
            $data = [
                'total_paid_amounts' => (float) $totalPaidAmounts,
                'pending_payouts_amount' => (float) $pendingPayoutsAmounts,
                'total_paid_this_month' => (float) $totalPaidThisMonth,
                'total_paid_previous_months' => (float) $totalPaidLastMonth,
                'monthly_payout_percentage_change' => $percentageChange,
                'products' => $products,
                'wallet'   => $wallet,
                'payouts'  => $payouts,
                'last_payout' => $last_payout,
            ];

            // Return the response
            return apiSuccess('All data loaded.',$data);
        } catch (\Exception $e) {

            // Log the error for debugging
            return apiError('An error occurred: ' . $e->getMessage());
        }
    }
    public function getCreatorPayouts(Request $request)
    {
        // Get the authenticated creator's ID
        $creatorId = auth()->id();

        // Base query
        $query = Payout::where('user_id', $creatorId)
        ->select('id', 'user_id', 'wallet_id', 'amount', 'currency', 'method', 'status', 'created_at');

        // Default dates
        $start = null;
        $end = now()->endOfDay();

        // Handle "Named" Filters
        if ($request->filled('filter')) {
            switch ($request->filter) {
                case 'today':
                    $start = now()->startOfDay();
                    break;
                case 'this_week':
                    $start = now()->startOfWeek();
                    break;
                case 'this_month':
                    $start = now()->startOfMonth();
                    break;
                case 'last_3_months':
                    $start = now()->subMonths(3)->startOfMonth();
                    break;
            }
        } 
        // Fallback to Custom Date Range if no named filter is provided
        elseif ($request->filled(['start_date', 'end_date'])) {
            $start = Carbon::parse($request->start_date)->startOfDay();
            $end = Carbon::parse($request->end_date)->endOfDay();
        }

        // Apply the filter if we have a start date
        if ($start) {
            $query->whereBetween('created_at', [$start, $end]);
        }

        return apiSuccess('Payouts retrieved.', $query->get());
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
