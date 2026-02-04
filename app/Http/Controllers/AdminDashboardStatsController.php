<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use App\Models\Product;
use App\Models\ProductClick;
use App\Models\Sale;
use App\Models\Storefront;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardStatsController extends Controller
{
    public function index()
    {
        $now = Carbon::now();

        // Date ranges
        $currentMonthStart  = $now->copy()->startOfMonth();
        $previousMonthStart = $now->copy()->subMonth()->startOfMonth();
        $previousMonthEnd   = $now->copy()->subMonth()->endOfMonth();

        // ---------- Total ------------
        $total_users = User::count();
        $total_storerfronts = Storefront::where('status','approved')->count();
        $total_earnings = Payout::where('status','paid')->sum('amount');
        $total_clicks = ProductClick::count();

        // ---------- CURRENT MONTH ----------
        $current_users = User::where('created_at', '>=', $currentMonthStart)->count();

        $current_storefronts = Storefront::where('status', 'approved')
            ->where('created_at', '>=', $currentMonthStart)
            ->count();

        $current_earnings = Payout::where('status', 'paid')
            ->where('created_at', '>=', $currentMonthStart)
            ->sum('amount');

        $current_clicks = ProductClick::where('created_at', '>=', $currentMonthStart)->count();

        // ---------- PREVIOUS MONTH ----------
        $previous_users = User::whereBetween('created_at', [
            $previousMonthStart, $previousMonthEnd
        ])->count();

        $previous_storefronts = Storefront::where('status', 'approved')
            ->whereBetween('created_at', [
                $previousMonthStart, $previousMonthEnd
            ])->count();

        $previous_earnings = Payout::where('status', 'paid')
            ->whereBetween('created_at', [
                $previousMonthStart, $previousMonthEnd
            ])
            ->sum('amount');

        $previous_clicks = ProductClick::whereBetween('created_at', [
            $previousMonthStart, $previousMonthEnd
        ])->count();

        // ---------- PERCENT CHANGE HELPER ----------
        $percentChange = function ($current, $previous) {
            if ($previous == 0) {
                return $current > 0 ? 100 : 0;
            }
            return round((($current - $previous) / $previous) * 100, 2);
        };


        $topProducts = Product::withCount('clicks')
            ->orderByDesc('clicks_count')
            ->with('storefront:id,name')
            ->with('user:id,name')
            ->with('first_image')
            ->limit(3)
            ->get();


        $topCreators = User::query()
            ->where('users.account_type', 'creator')
            ->leftJoin('wallets', 'wallets.user_id', '=', 'users.id')
            ->leftJoin('wallet_transactions', function ($join) {
                $join->on('wallet_transactions.wallet_id', '=', 'wallets.id')
                    ->where('wallet_transactions.source', 'sale_commission')
                    ->where('wallet_transactions.type', 'credit')
                    ->where('wallet_transactions.status', 'completed');
            })
            ->select(
                'users.*',
                DB::raw('COALESCE(SUM(wallet_transactions.amount), 0) as total_commission')
            )
            ->groupBy('users.id')
            ->orderByDesc('total_commission')
            ->with('storefront:id,user_id,name')
            ->limit(3)
            ->get();

        // Prepare data
        $data = [
            'users' => [
                'total' => $total_users,
                'current_month' => $current_users,
                'previous_month' => $previous_users,
                'change_percent' => $percentChange($current_users, $previous_users),
            ],

            'storefronts' => [
                'total' => $total_storerfronts,
                'current_month' => $current_storefronts,
                'previous_month' => $previous_storefronts,
                'change_percent' => $percentChange($current_storefronts, $previous_storefronts),
            ],

            'clicks' => [
                'total' => $total_clicks,
                'current_month' => $current_clicks,
                'previous_month' => $previous_clicks,
                'change_percent' => $percentChange($current_clicks, $previous_clicks),
            ],

            'earnings' => [
                'total' => $total_earnings,
                'current_month' => $current_earnings,
                'previous_month' => $previous_earnings,
                'change_percent' => $percentChange($current_earnings, $previous_earnings),
            ],

            'top_performing_products' => $topProducts,
            'top_performing_creators' => $topCreators,
        ];

        return apiSuccess('Admin dashboard stats retrieved successfully.', $data);
    }
    public function reports()
    {
        $now = Carbon::now();

        // Date ranges
        $currentWeekStart  = $now->copy()->startOfWeek();
        $previousWeekStart = $now->copy()->subWeek()->startOfWeek();
        $previousWeekEnd   = $now->copy()->subWeek()->endOfWeek();

        // ---------- Total ------------
        $total_creators = User::where('account_type','creator')->count();
        $total_earnings = Payout::where('status','paid')->sum('amount');
        $total_clicks = ProductClick::count();
        $total_sales = Sale::whereIn('event_type', ['CONFIRMATION', 'AMENDMENT'])->count();
        $total_conversion_rate = $total_clicks > 0
            ? min(100, round(($total_sales / $total_clicks) * 100, 2))
            : 0;

        // ---------- CURRENT MONTH ----------
        $current_creators = User::where('account_type','creator')->where('created_at', '>=', $currentWeekStart)->count();
        $current_earnings = Payout::where('status', 'paid')
            ->where('created_at', '>=', $currentWeekStart)
            ->sum('amount');

        $current_clicks = ProductClick::where('created_at', '>=', $currentWeekStart)->count();
        $current_sales = Sale::whereIn('event_type', ['CONFIRMATION', 'AMENDMENT'])->where('created_at', '>=', $currentWeekStart)->count();
        $current_conversion_rate = $current_clicks > 0
            ? min(100, round(($current_sales / $current_clicks) * 100, 2))
            : 0;

        // ---------- PREVIOUS MONTH ----------
        $previous_creators = User::where('account_type','creator')->whereBetween('created_at', [
            $previousWeekStart, $previousWeekEnd
        ])->count();

        $previous_earnings = Payout::where('status', 'paid')
            ->whereBetween('created_at', [
                $previousWeekStart, $previousWeekEnd
            ])
            ->sum('amount');

        $previous_clicks = ProductClick::whereBetween('created_at', [
            $previousWeekStart, $previousWeekEnd
        ])->count();

        $previous_sales = Sale::whereIn('event_type', ['CONFIRMATION', 'AMENDMENT'])->whereBetween('created_at', [
            $previousWeekStart, $previousWeekEnd
        ])->count();

        $previous_conversion_rate = $previous_clicks > 0
            ? min(100, round(($previous_sales / $previous_clicks) * 100, 2))
            : 0;

        // ---------- PERCENT CHANGE HELPER ----------
        $percentChange = function ($current, $previous) {
            if ($previous == 0) {
                return $current > 0 ? 100 : 0;
            }
            return round((($current - $previous) / $previous) * 100, 2);
        };


        $topProducts = Product::withCount('clicks')
            ->orderByDesc('clicks_count')
            ->with('storefront:id,name')
            ->with('user:id,name')
            ->with('first_image')
            ->limit(3)
            ->get();


        $topCreators = User::query()
            ->where('users.account_type', 'creator')
            ->leftJoin('wallets', 'wallets.user_id', '=', 'users.id')
            ->leftJoin('wallet_transactions', function ($join) {
                $join->on('wallet_transactions.wallet_id', '=', 'wallets.id')
                    ->where('wallet_transactions.source', 'sale_commission')
                    ->where('wallet_transactions.type', 'credit')
                    ->where('wallet_transactions.status', 'completed');
            })
            ->select(
                'users.*',
                DB::raw('COALESCE(SUM(wallet_transactions.amount), 0) as total_commission')
            )
            ->groupBy('users.id')
            ->orderByDesc('total_commission')
            ->with('storefront:id,user_id,name')
            ->limit(3)
            ->get();

        



        // Prepare data
        $data = [
            'creators' => [
                'total' => $total_creators,
                'current_week' => $current_creators,
                'previous_week' => $previous_creators,
                'change_percent' => $percentChange($current_creators, $previous_creators),
            ],

            'clicks' => [
                'total' => $total_clicks,
                'current_week' => $current_clicks,
                'previous_week' => $previous_clicks,
                'change_percent' => $percentChange($current_clicks, $previous_clicks),
            ],

            'earnings' => [
                'total' => $total_earnings,
                'current_week' => $current_earnings,
                'previous_week' => $previous_earnings,
                'change_percent' => $percentChange($current_earnings, $previous_earnings),
            ],

            'conversion_rate' => [
                'total' => $total_conversion_rate,
                'current_week' => $current_conversion_rate,
                'previous_week' => $previous_conversion_rate,
                'change_percent' => $percentChange($current_conversion_rate, $previous_conversion_rate),
            ],

            'top_performing_products' => $topProducts,
            'top_performing_creators' => $topCreators,
        ];

        return apiSuccess('Admin dashboard stats retrieved successfully.', $data);
    }
}
