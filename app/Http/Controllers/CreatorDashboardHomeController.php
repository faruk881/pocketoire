<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Product;
use App\Models\ProductClick;
use App\Models\Sale;
use App\Models\Album;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class CreatorDashboardHomeController extends Controller
{
    public function home(Request $request)
    {
        $storefront = auth()->user()->storefront;
        $user = auth()->user();
        if (!$storefront) return [];

        $startThisMonth = now()->startOfMonth();
        $startLastMonth = now()->subMonth()->startOfMonth();
        $endLastMonth   = now()->subMonth()->endOfMonth();

        $productIds = Product::where('storefront_id', $storefront->id)->pluck('id');

        /**
         * =========================
         * TOTALS (LIFETIME)
         * =========================
         */
        $totalProducts = $productIds->count();

        $totalClicks = ProductClick::whereIn('product_id', $productIds)->count();

        $totalEarnings = Sale::where('user_id', $user->id)
            ->where('status', 'confirmed')
            ->sum('creator_commission');

        /**
         * =========================
         * THIS MONTH vs LAST MONTH
         * =========================
         */
        // PRODUCTS
        $productsLastMonth = Product::where('storefront_id', $storefront->id)
            ->whereBetween('created_at', [$startLastMonth, $endLastMonth])
            ->count();

        $productsThisMonth = Product::where('storefront_id', $storefront->id)
            ->where('created_at', '>=', $startThisMonth)
            ->count();

        // CLICKS
        $clicksLastMonth = ProductClick::whereIn('product_id', $productIds)
            ->whereBetween('created_at', [$startLastMonth, $endLastMonth])
            ->count();

        $clicksThisMonth = ProductClick::whereIn('product_id', $productIds)
            ->where('created_at', '>=', $startThisMonth)
            ->count();

        // EARNINGS
        $earningsLastMonth = Sale::where('user_id', $user->id)
            ->where('status', 'confirmed')
            ->whereBetween('created_at', [$startLastMonth, $endLastMonth])
            ->sum('creator_commission');

        $earningsThisMonth = Sale::where('user_id', $user->id)
            ->where('status', 'confirmed')
            ->where('created_at', '>=', $startThisMonth)
            ->sum('creator_commission');

        /**
         * =========================
         * RECENT 3 PRODUCTS
         * =========================
         */
        $recentProducts = Product::where('storefront_id', $storefront->id)
            ->latest()
            ->take(3)
            ->get()
            ->map(fn ($product) => [
                'id' => $product->id,
                'title' => $product->title,
                'clicks' => $product->clicks()->count(),
                'earnings' => $product->sales()
                    ->where('status', 'confirmed')
                    ->sum('creator_commission'),
            ]);

        /**
         * =========================
         * ALBUM STATS
         * =========================
         */
        $albums = Album::where('storefront_id', $storefront->id)
            ->with('products:id,album_id')
            ->get()
            ->map(function ($album) {

                $productIds = $album->products->pluck('id');

                $totalClicks = ProductClick::whereIn('product_id', $productIds)
                    ->selectRaw('COUNT(DISTINCT COALESCE(user_id, visitor_id)) as total')
                    ->value('total');

                return [
                    'name' => $album->name,
                    'description' => $album->description,
                    'products_count' => $album->products->count(),
                    'total_clicks' => $totalClicks,
                    'total_earnings' => Sale::whereIn('product_id', $productIds)
                        ->where('status', 'confirmed')
                        ->sum('creator_commission'),
                ];
            });

        return [
            'totals' => [
                'products' => [
                    'total' => $totalProducts,
                    'change_percent' => self::percentChange($productsLastMonth, $productsThisMonth),
                ],
                'clicks' => [
                    'total' => $totalClicks,
                    'change_percent' => self::percentChange($clicksLastMonth, $clicksThisMonth),
                ],
                'earnings' => [
                    'total' => round($totalEarnings, 2),
                    'change_percent' => self::percentChange($earningsLastMonth, $earningsThisMonth),
                ],
            ],
            'recent_products' => $recentProducts,
            'albums' => $albums,
        ];
    }

    /**
     * Percent change helper
     */
    private static function percentChange(float|int $old, float|int $new): float
    {
        if ($old == 0) {
            return $new > 0 ? 100 : 0;
        }

        return round((($new - $old) / $old) * 100, 2);
    }
}
