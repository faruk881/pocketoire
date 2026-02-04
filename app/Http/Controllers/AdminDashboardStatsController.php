<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use App\Models\ProductClick;
use App\Models\Storefront;
use App\Models\User;
use Illuminate\Http\Request;

class AdminDashboardStatsController extends Controller
{
    public function index()
    {
        $total_users = User::count();
        $total_storerfronts = Storefront::where('status','approved')->count();
        $total_earnings = Payout::where('status','paid')->sum('amount');


        return apiSuccess('Admin dashboard stats retrieved successfully.', [
            'total_users' => $total_users,
            'total_storerfronts' => $total_storerfronts,
            'total_earnings' => $total_earnings
        ]);
    }
}
