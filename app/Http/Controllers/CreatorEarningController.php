<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;

class CreatorEarningController extends Controller
{
    public function getCreatorEarnings()
    {
        $creatorId = auth()->id();

        $sales = Sale::query()
            ->where('sales.user_id', $creatorId) // 👈 FIX
            ->whereIn('sales.status', ['confirmed', 'amended']) // 👈 also qualify
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

        return apiSuccess('All data loaded.', [
            'products' => $sales->map(fn ($row) => [
                'id' => $row->product_id,               // NULL if product missing
                'product_code' => $row->product_code,   // ALWAYS available
                'title' => $row->title ?? 'Unlisted Product',
                'main_image' => $row->main_image,
                'total_conversions' => (int) $row->total_conversions,
                'total_clicks' => 0, // clicks impossible without product
                'total_earnings' => (float) $row->total_earnings,
            ])
        ]);
    }
}
