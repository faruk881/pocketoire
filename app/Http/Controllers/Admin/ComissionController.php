<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\addComissionToCreatorRequest;
use App\Models\Sale;
use Illuminate\Http\Request;

class ComissionController extends Controller
{
    public function addComissionToCreator(addComissionToCreatorRequest $request)
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
}
