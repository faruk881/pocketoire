<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCreatorRequest;
use App\Http\Requests\UpdateCreatorStatusRequest;
use App\Http\Requests\UpdateCreatorStorefrontStatusRequest;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UsersController extends Controller
{
    public function getUsers(Request $request) {
        try {

            // The $role is came from route defaults
            $role = $request->route('role'); 

            // Pagination size with limits
            $paginate = min(max((int) $request->query('per_page', 10), 1), 100);

            // Subqueries for clicks
            $clicksSub = DB::table('products')
                ->join('product_clicks', 'product_clicks.product_id', '=', 'products.id')
                ->select(
                    'products.user_id',
                    DB::raw('COUNT(product_clicks.id) as total_clicks')
                )
                ->groupBy('products.user_id');

            // Subqueries for commissions
            $commissionSub = DB::table('wallets')
                ->join('wallet_transactions', function ($join) {
                    $join->on('wallet_transactions.wallet_id', '=', 'wallets.id')
                        ->where('wallet_transactions.source', 'sale_commission')
                        ->where('wallet_transactions.type', 'credit')
                        ->where('wallet_transactions.status', 'completed');
                })
                ->select(
                    'wallets.user_id',
                    DB::raw('SUM(wallet_transactions.amount) as total_commission')
                )
                ->groupBy('wallets.user_id');

            if($role === 'creator') {
                // Query results
                $query = User::query()
                    ->where('users.account_type', $role)

                    ->leftJoinSub($clicksSub, 'clicks', function ($join) {
                        $join->on('clicks.user_id', '=', 'users.id');
                    })
                    ->leftJoinSub($commissionSub, 'commissions', function ($join) {
                        $join->on('commissions.user_id', '=', 'users.id');
                    })

                    ->select([
                        'users.id',
                        'users.name',
                        'users.email',
                        'users.status',
                        'users.created_at',
                        DB::raw('COALESCE(clicks.total_clicks, 0) as total_clicks'),
                        DB::raw('COALESCE(commissions.total_commission, 0) as total_commission'),
                    ])
                    ->with('storefront:id,user_id,name,slug,status');
            }

            if($role === 'buyer') {
                    $query = User::query()
                    ->where('users.account_type', $role)

                    ->select([
                        'users.id',
                        'users.name',
                        'users.email',
                        'users.status',
                        'users.created_at',
                    ]);
            }
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

    public function getBuyers(){

    }

    public function getProfile(Request $request, $id) {
        // Get the role from routes default method
        $role = $request->route('role'); 

        // Get users
        $user = User::where('id',$id)
            ->select('id','name','email','account_type','created_at','profile_photo','cover_photo','status')
            ->with('storefront:id,user_id,name,slug,bio')
            ->first();
        
        // Check if user exists
        if(!$user){
            return apiError(ucfirst($role).' User not found',404);
        }

        // Get Products
        $products = Product::where('storefront_id', $user->storefront->id)
            ->with('product_image')
            ->withCount('clicks')
            ->withCount('sales')
            ->withSum('sales','creator_commission')
            ->select('id','title','description','product_link','price')
            ->paginate(4);

        // Generates: http://yoursite.com/api/click/4
        $products->getCollection()->transform(function ($product) {
            $product->product_link = route('product.track', ['id' => $product->id]);
            return $product;
        });

        $total_products = $products->count();
        //Prepare the data
        $data = [
            'user' => $user,
            'products' => $products,
            'total_products' => $total_products,
        ];


        // return the results
        return apiSuccess(ucfirst($role).' Profile retrieved successfully.', $data);
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

    public function deleteProfile($id) {
        try {
                    // Check if the user is an admin
            if(auth()->user()->account_type !== 'admin') {
                return apiError('You are not authorized to perform this action.', 403);
            }

            // Get the user
            $user = User::find($id);

            // Check if user exists
            if (!$user) {
                return apiError('User not found', 404);
            }  

            // Delete profile photo if exists
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            // Delete cover photo if exists
            if ($user->cover_photo && Storage::disk('public')->exists($user->cover_photo)) {
                Storage::disk('public')->delete($user->cover_photo);
            }
            
            // Delete the user
            $user->delete();

            // Return the success message
            return apiSuccess('User deleted successfully.');

        } catch (\Throwable $e) {
            // Handle errors
            return apiError($e->getMessage());
        }

    }
}       