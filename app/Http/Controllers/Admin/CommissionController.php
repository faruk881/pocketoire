<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\addcommissionToCreatorRequest;
use App\Http\Requests\CustomComissionRequest;
use App\Http\Requests\GlobalComissionRequest;
use App\Http\Requests\UpdateCustomCommissionRequest;
use App\Models\CommissionSetting;
use App\Models\CreatorCommissionOverrides;
use App\Models\Sale;
use App\Models\User;
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

    public function payoutView(){
        try {
            // Get global commission setting
            $global_comission_percent = CommissionSetting::where('active', true)->first();

            // Get custom commission overrides with user details
            $custom_comission_percent = CreatorCommissionOverrides::with('user:id,name,email')->get();

            // Prepare response data
            $data['global_creator_commission_percent'] = $global_comission_percent->global_creator_commission_percent;
            $data['custom_creator_commission'] = $custom_comission_percent?:null;

            $data['creators'] = User::where('account_type', 'creator')
                ->select('id', 'name', 'email')
                ->with('storefront:id,user_id,name')
                ->with('wallet:id,user_id,balance,status')
                ->get();

            // Return response
            return apiSuccess('Payout settings loaded.', $data);
        } catch (\Exception $e) {
            return apiError('An error occurred: ' . $e->getMessage());
        }
    }

    public function updateGlobalCommission(GlobalComissionRequest $request){
        try{
            // Check if a global commission setting already exists
            $commissionSetting = CommissionSetting::where('active', true)->first();

            if(!$commissionSetting){
                // Create new setting
                CommissionSetting::create([
                    'global_creator_commission_percent' => $request->global_creator_commission_percent,
                    'active'                     => true,
                ]);
            } else {
                // Update existing setting
                $commissionSetting->update([
                    'global_creator_commission_percent' => $request->global_creator_commission_percent,
                ]);
            }

            // Return success response
            return apiSuccess('Global commission percent updated successfully.', [
                'global_creator_commission_percent' => $request->global_creator_commission_percent,
            ]);
        } catch (\Exception $e) {
            return apiError('An error occurred: ' . $e->getMessage());
        }
    }

    public function createCustomCommission(CustomComissionRequest $request){
        try{

            // Get the user
            $user = User::find($request->user_id);

            // Check if user exists
            if (!$user) {
                return apiError('User not found', 404);
            }

            // Check if user is a creator
            if(!$user->isCreator()){
                return apiError('The specified user is not a creator.');
            }

            // Check if custom commission already exists for this creator
            if($user->creator_comission_override){
                return apiError('Custom commission already exists for this creator.');
            }

            // Create the custom commission override
            $creatorCommissionOverrides = CreatorCommissionOverrides::Create(
                ['user_id' => $request->user_id],
                [
                 'creator_commission_percent' => $request->creator_commission_percent,
                 'effective_from' => $request->effective_from,
                 'effective_to' => $request->effective_to,
                ]
            );

            // Save the custom commission override
            $creatorCommissionOverrides->save();

            return apiSuccess('Custom commission percent updated successfully.',$creatorCommissionOverrides);
        } catch (\Exception $e) {
            return apiError('An error occurred: ' . $e->getMessage());
        }
    }

    public function updateCustomCommission(UpdateCustomCommissionRequest $request, $id){
        try{
            // Get the custom commission override
            $creatorCommissionOverrides = CreatorCommissionOverrides::find($id);

            // Check if custom commission override exists
            if (!$creatorCommissionOverrides) {
                return apiError('Custom commission override not found', 404);
            }   

            // Update the custom commission override
            $creatorCommissionOverrides->update([
                'creator_commission_percent' => $request->creator_commission_percent,
                'effective_from' => $request->effective_from,
                'effective_to' => $request->effective_to,
            ]);

            return apiSuccess('Custom commission percent updated successfully.',$creatorCommissionOverrides);
        } catch (\Exception $e) {
            return apiError('An error occurred: ' . $e->getMessage());
        }
    }
    
    public function addCustomCommissionView(){
        try{
            // Get all creators who do not have a custom commission override
            $creators = User::where('account_type', 'creator')
                        ->whereNotIn('id', function($query) {
                            $query->select('user_id')
                                  ->from('creator_commission_overrides');
                        })
                        ->select('id', 'name', 'email')
                        ->get();

            // Return the list of creators
            return apiSuccess('Creators without custom commission loaded.', ['creators' => $creators]);
        } catch (\Exception $e) {
            return apiError('An error occurred: ' . $e->getMessage());
        }
    }


}
