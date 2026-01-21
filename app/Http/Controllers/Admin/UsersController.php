<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCreatorRequest;
use App\Http\Requests\UpdateCreatorStatusRequest;
use App\Http\Requests\UpdateCreatorStorefrontStatusRequest;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Models\User;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    public function getUsers(Request $request) {
        try {

            // The $role is came from route defaults
            $role = $request->route('role'); 

            // Pagination size with limits
            $paginate = min(max((int) $request->query('per_page', 10), 1), 100);

            // Base query
            $query = User::where('account_type', $role)
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
            if ($role === 'creator' && $request->filled('storefront_status') && in_array($request->storefront_status, ['pending','approved','rejected','banned'])) {
                $storefrontStatus = $request->storefront_status;

                $query->whereHas('storefront', function ($sf) use ($storefrontStatus) {
                    $sf->where('status', $storefrontStatus);
                });
            }

            // Paginate the results
            $creators = $query->paginate($paginate);

            // Return paginated creators
            return apiSuccess(ucfirst($role).'s retrieved successfully.', $creators);

        } catch (\Throwable $e) {
            // Handle errors
            return apiError($e->getMessage());
        }
    }

    public function updateUserStatus(UpdateUserStatusRequest $request, $id) {
        try {
            // The $role is came from route defaults
            $role = $request->route('role');
            // Find the user
            $creator = User::where('account_type', $role)->findOrFail($id);
            if($creator->status === $request->status){
                return apiError('The creator already has the specified status.',409);
            }

            if($request->status !== 'active' && !$request->filled('status_reason')) {
                return apiError('Status reason is required when suspending or banning a creator.');
            }

            // Update status
            $creator->update([
                'status' => $request->status,
                'status_reason' => $request->input('status_reason'),
            ]);

            // Return success response
            return apiSuccess('Creator status updated successfully.', 
            [
                'id' => $creator->id, 
                'status' => $creator->status,
                'status_reason' => $request->status !== 'active' ? $creator->status_reason : null
            ]);
        } catch (\Throwable $e) {
            // Handle other errors
            return apiError($e->getMessage());
        }

    }

    public function updateCreatorStorefrontStatus(UpdateCreatorStorefrontStatusRequest $request, $id) {
        try {
            // Find the creator's storefront
            $creator = User::where('account_type', 'creator')->findOrFail($id);
            $storefront = $creator->storefront;

            if (!$storefront) {
                return apiError('The creator does not have a storefront.', 404);
            }

            if ($storefront->status === $request->status) {
                return apiError('The storefront already has the specified status.', 409);
            }

            if ($request->status !== 'approved' && !$request->filled('status_reason')) {
                return apiError('Status reason is required when rejecting or banning a storefront.');
            }

            // Update storefront status
            $storefront->update([
                'status' => $request->status,
                'status_reason' => $request->input('status_reason'),
            ]);

            // Return success response
            return apiSuccess('Storefront status updated successfully.', 
            [
                'id' => $storefront->id, 
                'status' => $storefront->status,
                'status_reason' => $request->status !== 'approved' ? $storefront->status_reason : null
            ]);
        } catch (\Throwable $e) {
            // Handle other errors
            return apiError($e->getMessage());
        }
    }
}       