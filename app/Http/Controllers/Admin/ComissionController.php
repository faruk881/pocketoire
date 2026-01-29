<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\addComissionToCreatorRequest;
use App\Models\Sale;
use Illuminate\Http\Request;

class ComissionController extends Controller
{
    public function addCreatorComission(addComissionToCreatorRequest $request)
    {
        $sale = Sale::findOrFail($request->id);

        if (!in_array($sale->event_type, ['CONFIRMATION', 'AMENDMENT'])) {
            return apiError('Operation not supported for this sale');
        }

        $sale->affiliate_comission = $request->affiliate_comission;
        $sale->creator_comission = ($sale->affiliate_comission * $request->creator_comission_percent) / 100;
        $sale->creator_comission_percent = $request->creator_comission_percent;
        $sale->save();

        return apiSuccess(
            'Commission settings updated successfully.',
            [
                'product_id'          => $sale->id,
                'affiliate_comission' => $sale->affiliate_comission,
                'comission_percent'   => $sale->creator_comission_percent,
                'creator_comission'   => $sale->creator_comission,
            ]
        );
    }

    public function viewCreatorComission(Request $request){
        $perPage  = $request->get('per_page', 10);
        $latestSaleIds = Sale::selectRaw('MAX(id)')
        ->groupBy('booking_ref');
        $sales = Sale::select('id',
                            'product_id',
                            'user_id',
                            'booking_ref',
                            'event_type',
                            'campaign_value',
                            'affiliate_comission',
                            'creator_comission',
                            'creator_comission_percent')
                            ->whereIn('id', $latestSaleIds)
                            ->with(['product:id,title',
                                    'user' => function($query) {
                                        $query->select('id', 'name', 'email')
                                            ->with('storefront:id,user_id,name'); // Change 'name' to your actual column like 'store_name'
                                        }
                                    ])
                            ->latest('id')
                            ->paginate($perPage);

        return apiSuccess('All comissions loaded.', ['sales' => $sales]);
    }
}
