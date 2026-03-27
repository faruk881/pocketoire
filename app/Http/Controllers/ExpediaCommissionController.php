<?php

namespace App\Http\Controllers;

use App\Models\CommissionSetting;
use App\Models\CreatorCommissionOverrides;
use App\Models\ExpediaSale;
use App\Models\Product;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpediaCommissionController extends Controller
{

    public function viewExpediacommission(Request $request) {
        try {
            // Pagination
            $per_page = $request->get('per_page', 10);

            // Get sales
            $expedia_sale = ExpediaSale::with('product')->paginate($per_page);

            // return
            return apiSuccess('Expedia commission retrieved successfully.', $expedia_sale);
        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }
    }
    public function getExpediaProducts() {
        try {
            $expediaProducts = Product::where('status','approved')
            ->where('source','expedia')
            ->with('product_image')
            ->with('storefront')
            ->get();
            return apiSuccess('Expedia products retrieved successfully.', $expediaProducts);
        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }
    }

    public function getCreators() {
        try {
            $users = User::where('account_type','creator')
            ->where('status','active')
            ->with('storefront')
            ->get();
            return apiSuccess('Users retrieved successfully.', $users);
        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }
    }

    public function addExpediacommission(Request $request) {
        try {
            // Get the product
            $product = Product::where('id',$request->product_id)->first();

            // Check if product exists
            if(!$product) {
                return apiError('product not found');
            }

            // Get the user
            $user = $product->user;

                    // Get platform commission amount 
            $platformCommission = round((float) $request->platform_commission, 2);

            // Get Commission percent.
            // If user has custom commission percent then it will use it.
            // Otherwise it will use global commission percent.
            $percent = (float) (
                CreatorCommissionOverrides::where('user_id', $user->id)
                    ->value('creator_commission_percent')
                ?? CommissionSetting::value('global_creator_commission_percent')
            );

            // Calculate Creator Commission
            $creatorCommission = round(($platformCommission * $percent) / 100,2);

            // Save commission values
            DB::beginTransaction();
            $expedia_sale = ExpediaSale::create([
                'product_id'                 => $product->id,
                'user_id'                    => $user->id,
                'platform_commission'        => $platformCommission,
                'creator_commission_percent' => $percent,
                'creator_commission'         => $creatorCommission,
                'currency'                   => 'USD',
                'wallet_credited_at' => now()

            ]);

                        /**
             * CREDIT WALLET (only if creator exists)
             */
            if ($user && $creatorCommission > 0 ) {

                // Get wallet
                $wallet = $user->wallet;

                // Check if wallet exists
                if (!$wallet || $wallet->status !== 'active') {
                    throw new \Exception('Creator wallet not available');
                }

                // Get commission before and after
                $balanceBefore = $wallet->balance;
                $balanceAfter  = $balanceBefore + $creatorCommission;

                // Update wallet balance
                $wallet->update([
                    'balance' => $balanceAfter,
                ]);

                // Ledger entry
                WalletTransaction::create([
                    'wallet_id'      => $wallet->id,
                    'expedia_sale_id' => $expedia_sale->id,
                    'type'           => 'credit',
                    'source'         => 'sale_commission',
                    'amount'         => $creatorCommission,
                    'balance_before' => $balanceBefore,
                    'balance_after'  => $balanceAfter,
                    'status'         => 'completed',
                    'metadata'       => [
                        'product_from' => 'expedia',
                    ],
                ]);
            }

            // If there is no error then commit
            DB::commit();

            return apiSuccess('Expedia commission updated successfully', $expedia_sale);


            // Return
            return apiSuccess('produce',$user);


        } catch (\Throwable $e) {
            DB::rollBack();
            return apiError($e->getMessage());
        }
    }


}
