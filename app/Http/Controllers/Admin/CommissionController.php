<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\addcommissionToCreatorRequest;
use App\Models\Sale;
use Illuminate\Http\Request;
use Stripe\Card;

class commissionController extends Controller
{
    public function addCreatorcommission(addcommissionToCreatorRequest $request)
    {
        try {
            $sale = Sale::find($request->id);
            if (!$sale) {
                return apiError('Sale not found', 404);
            }

            if (!in_array($sale->event_type, ['CONFIRMATION', 'AMENDMENT'])) {
                return apiError('Operation not supported for this sale');
            }

            $sale->platform_commission = $request->platform_commission;
            $sale->creator_commission = ($sale->platform_commission * $request->creator_commission_percent) / 100;
            $sale->creator_commission_percent = $request->creator_commission_percent;
            $sale->save();

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
            return apiError('An error occurred: ' . $e->getMessage());
        }
    }

    public function viewCreatorcommission(Request $request){
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
    }
}
