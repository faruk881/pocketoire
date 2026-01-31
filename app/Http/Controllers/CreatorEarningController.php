<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\Wallet;
use Illuminate\Http\Request;

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
            $data['products'] = $sales->map(fn ($row) => [
                    'id' => $row->product_id,               // NULL if product missing
                    'product_code' => $row->product_code,   // ALWAYS available
                    'title' => $row->title ?? 'Unlisted Product',
                    'main_image' => $row->main_image,
                    'total_conversions' => (int) $row->total_conversions,
                    'total_clicks' => 0, // clicks impossible without product
                    'total_earnings' => (float) $row->total_earnings,
                ]);

            $data['wallet'] = Wallet::where('user_id', $creatorId)
            ->select('balance','currency','status')                
            ->first();

            return apiSuccess('All data loaded.',$data);
        } catch (\Exception $e) {
            return apiError('An error occurred: ' . $e->getMessage());
        }
    }
}
