<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    public function getCreators(Request $request) {
        try {

            // Pagination size with limits
            $paginate = min(max((int) $request->query('per_page', 10), 1), 100);

            // Base query
            $query = User::where('account_type', 'creator')
                ->select(['id', 'name', 'email', 'status', 'created_at'])
                ->with('storefront:id,user_id,name,slug,status');

            // Search by keywords (user + users storefront)
            if ($request->filled('keywords')) {
                $keywords = $request->keywords;

                $query->where(function ($q) use ($keywords) {
                    $q->where('name', 'like', "%{$keywords}%")
                    ->orWhere('email', 'like', "%{$keywords}%")
                    ->orWhereHas('storefront', function ($sf) use ($keywords) {
                        $sf->where('name', 'like', "%{$keywords}%")
                            ->orWhere('slug', 'like', "%{$keywords}%");
                    });
                });
            }

            // Filter by user status
            if ($request->filled('user_status') && in_array($request->user_status, ['active','suspended','banned'])) {
                $query->where('status', $request->user_status);
            }

            // Filter by storefront status
            if ($request->filled('storefront_status') && in_array($request->storefront_status, ['pending','approved','rejected','banned'])) {
                $storefrontStatus = $request->storefront_status;

                $query->whereHas('storefront', function ($sf) use ($storefrontStatus) {
                    $sf->where('status', $storefrontStatus);
                });
            }

            // Paginate the results
            $creators = $query->paginate($paginate);

            // Return paginated creators
            return apiSuccess('Creators retrieved successfully.', $creators);

        } catch (\Throwable $e) {
            // Handle errors
            return apiError($e->getMessage());
        }
    }
}
