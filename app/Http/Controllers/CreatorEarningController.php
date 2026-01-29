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

        $products = Product::with('product_images')->withCount([
            'sales as total_sales' => function ($query) {
                $query->whereIn('status', ['confirmed','amended']); // only consider non-cancelled
            },
            'clicks as total_clicks',
        ])->withSum([
            'sales as total_earnings' => function ($query) {
                $query->whereIn('status', ['confirmed','amended']); // only count earnings from valid sales
            }
        ], 'creator_comission')
        ->where('user_id', $creatorId)
        ->get()
        ->map(function($product){
            return [
                'id' => $product->id,
                'title' => $product->title,
                'main_image' => $product->product_images[0]->image ?? null,
                'total_conversions' => $product->total_sales ?? 0,
                'total_clicks' => $product->total_clicks ?? 0,
                'total_earnings' => $product->total_earnings ?? 0,
            ];
        });

        return apiSuccess('All data loaded.', ['products' => $products]);
    }
}
