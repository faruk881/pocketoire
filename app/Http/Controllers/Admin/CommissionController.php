<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\addcommissionToCreatorRequest;
use App\Models\Sale;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Card;

class commissionController extends Controller
{
    public function addCreatorcommission(addcommissionToCreatorRequest $request)
    {
        DB::beginTransaction();
        try {
            $sale = Sale::find($request->id);
            if (!$sale) {
                return apiError('Sale not found', 404);
            }

            // only valid confirmed/ amended sales can have commission added.
            if (!in_array($sale->event_type, ['CONFIRMATION', 'AMENDMENT'])) {
                return apiError('Operation not supported for this sale');
            }

            $platformCommission = round((float) $request->platform_commission, 2);
            $percent            = (float) $request->creator_commission_percent;
            $creatorCommission = round(($platformCommission * $percent) / 100,2);

                    // Save commission values
            $sale->update([
                'platform_commission'        => $platformCommission,
                'creator_commission_percent' => $percent,
                'creator_commission'         => $creatorCommission,
            ]);

            /**
             * CREDIT WALLET (only if creator exists)
             */
            if ($sale->user && $creatorCommission > 0 && !$sale->wallet_credited_at) {

                $wallet = $sale->user->wallet;

                if (!$wallet || $wallet->status !== 'active') {
                    throw new \Exception('Creator wallet not available');
                }

                $balanceBefore = $wallet->balance;
                $balanceAfter  = $balanceBefore + $creatorCommission;

                // Update wallet balance
                $wallet->update([
                    'balance' => $balanceAfter,
                ]);

                // Ledger entry
                WalletTransaction::create([
                    'wallet_id'      => $wallet->id,
                    'sale_id'        => $sale->id,
                    'type'           => 'credit',
                    'source'         => 'sale_commission',
                    'amount'         => $creatorCommission,
                    'balance_before' => $balanceBefore,
                    'balance_after'  => $balanceAfter,
                    'status'         => 'completed',
                    'metadata'       => [
                        'product_code' => $sale->product_code,
                        'event_type'   => $sale->event_type,
                    ],
                ]);

                // Mark sale as credited
                $sale->update([
                    'wallet_credited_at' => now(),
                ]);
            }
            DB::commit();

            return apiSuccess(
                'Commission updated successfully.',
                [
                    'product_id'          => $sale->id,
                    'platform_commission' => $sale->platform_commission,
                    'commission_percent'   => $sale->creator_commission_percent,
                    'creator_commission'   => $sale->creator_commission,
                ]
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return apiError('An error occurred: ' . $e->getMessage());
        }
    }

    public function viewCreatorcommission(Request $request){
        try{
            $perPage  = $request->get('per_page', 10);
            $latestSaleIds = Sale::selectRaw('MAX(id)')
            ->groupBy('booking_ref');
            $sales = Sale::select('id',
                                'product_id',
                                'user_id',
                                'booking_ref',
                                'event_type',
                                'campaign_value',
                                'platform_commission',
                                'creator_commission',
                                'creator_commission_percent')
                                ->whereIn('id', $latestSaleIds)
                                ->whereNotNull('user_id')
                                ->with(['product:id,title',
                                        'user' => function($query) {
                                            $query->select('id', 'name', 'email')
                                                ->with('storefront:id,user_id,name'); // Change 'name' to your actual column like 'store_name'
                                            }
                                        ])
                                ->latest('id')
                                ->paginate($perPage);

            return apiSuccess('All commissions loaded.', ['sales' => $sales]);
        } catch (\Exception $e) {
            return apiError('An error occurred: ' . $e->getMessage());
        }
    }
}
