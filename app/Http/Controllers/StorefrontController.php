<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateStorefrontRequest;
use App\Http\Requests\SearchProductRequest;
use App\Http\Requests\SearchStorefrontRequest;
use App\Http\Requests\StorefrontUrlCheckRequest;
use App\Http\Requests\StoreProductRequest;
use App\Models\Product;
use App\Models\Storefront;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\SetupIntent;

class StorefrontController extends Controller
{   
    public function getStorefronts(SearchStorefrontRequest $request) {
        try {
            $perPage = $request->get('per_page', 10);
            $search  = $request->get('search');
            $sort    = $request->get('sort'); // <--- Changed from $filter

            $query = Storefront::select('id', 'user_id', 'name', 'bio')
                ->withCount(['products as total_sold' => function ($q) {
                    $q->whereIn('status', ['confirmed', 'pending_price']);
                }])
                // Count 2: Total Products (Listed)
                ->withCount('products as total_products')
                ->with('user:id,name');


            // --- SEARCH (Filtering results) ---
            $query->when($search, function ($q) use ($search) {
                return $q->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('bio', 'LIKE', "%{$search}%");
                });
            });

            // --- SORT (Reordering results) ---
            $query->when($sort, function ($q) use ($sort) {
                switch ($sort) {
                    case 'popular':
                        return $q->orderByDesc('total_sold');
                    case 'newest':
                        return $q->orderByDesc('created_at');
                    case 'oldest':
                        return $q->orderBy('created_at');
                    case 'name_asc':
                        return $q->orderBy('name', 'asc');
                    case 'name_desc':
                        return $q->orderBy('name', 'desc');
                    default:
                        return $q->orderByDesc('created_at'); // Default sort
                }
            });

            $storefronts = $query->paginate($perPage);

        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }

        return apiSuccess('Storefronts retrieved successfully.', $storefronts);
    }

    public function createStorefront(CreateStorefrontRequest $request, StripeService $stripeService) {

        // Get current user.
        $user = auth()->user();

        // One storefront per user
        if ($user->storefront) {
            return apiError('You already have a storefront.', 403);
        }

        // Fetch Data
        $data = $request->validated();

        // null clientSected variable
        $clientSecret = null;

        // -----------------------------
        // STRIPE (outside transaction)
        // Create and save stripe customer id
        // -----------------------------
        if (! $user->stripe_customer_id) {
            $stripeCustomer = $stripeService->createCustomer($user);

            $user->stripe_customer_id = $stripeCustomer->id;
            $user->save();
        }

        // Load stripe secrets
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        // Create setup intent for frontend to save card.
        $setupIntent = SetupIntent::create([
            'customer' => $user->stripe_customer_id,
            'payment_method_types' => ['card'],
        ]);

        // Put the secrets to previously created empty variable
        $clientSecret = $setupIntent->client_secret;

        // -----------------------------
        // DATABASE TRANSACTION
        // -----------------------------

        DB::beginTransaction();

        try {
            // Create storefront
            $storefront = Storefront::create([
                'user_id' => $user->id,
                'name'    => $data['storename'],
                'slug'    => Str::slug($data['storeurl']),
                'status'  => 'pending',
                'bio'     => $data['description'] ?? null,
            ]);

            // Update user profile assets
            $userData = [];

            if ($request->hasFile('profile_photo')) {
                $userData['profile_photo'] = $request
                    ->file('profile_photo')
                    ->store('users/profile', 'public');
            }

            if ($request->hasFile('cover_photo')) {
                $userData['cover_photo'] = $request
                    ->file('cover_photo')
                    ->store('users/cover', 'public');
            }

            // Change account type to creator
            $userData['account_type'] = 'creator';

            if (! empty($userData)) {
                $user->update($userData);
            }

            DB::commit();
            // End of transition.

            return apiSuccess(
                'Storefront created successfully. Waiting for approval.',
                [
                    'storefront' => $storefront,
                    'user' => $user->only(['profile_photo', 'cover_photo', 'stripe_customer_id']),
                    'client_secret' => $clientSecret
                ],
                201
            );

        } catch (\Throwable $e) {
            DB::rollBack();

            return apiError(
                'Failed to create storefront.',
                500,
                ['exception' => $e->getMessage()]
            );
        }
    } // End of createStorefront

    public function storefrontUrlCheck(StorefrontUrlCheckRequest $request) {
        try {
            $data = $request->validated();
            $slug = Str::slug($data['storeurl']);
            $slug_is_exist = Storefront::where('slug',$slug)->first();
            if($slug_is_exist) {
                return apiError('The url already taken');
            } else {
                return apiSuccess('You can use this url');
            }
        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }

    }

    public function storefrontProfile(SearchProductRequest $request) {
        try {
            $user = auth()->user();

            // 1. Safety Check: Ensure User has a Storefront
            if (!$user->storefront) {
                return apiError('You do not have a storefront yet.');
            }

            // 2. Get Input Parameters
            $perPage  = $request->get('per_page', 10);
            $search   = $request->get('search');
            $sort     = $request->get('sort');
            $minPrice = $request->get('min_price');
            $maxPrice = $request->get('max_price');

            // 3. Start Product Query
            $query = Product::where('storefront_id', $user->storefront->id);

            // --- FILTERING & SORTING LOGIC (Same as before) ---
            $query->when($minPrice, function ($q) use ($minPrice) {
                return $q->where('price', '>=', $minPrice);
            });
            $query->when($maxPrice, function ($q) use ($maxPrice) {
                return $q->where('price', '<=', $maxPrice);
            });
            $query->when($search, function ($q) use ($search) {
                return $q->where(function ($subQuery) use ($search) {
                    $subQuery->where('title', 'LIKE', "%{$search}%")
                             ->orWhere('vaitor_product_code', 'LIKE', "%{$search}%");
                });
            });
            $query->when($sort, function ($q) use ($sort) {
                switch ($sort) {
                    case 'price_low': return $q->orderBy('price', 'asc');
                    case 'price_high': return $q->orderBy('price', 'desc');
                    case 'title_asc': return $q->orderBy('title', 'asc');
                    default: return $q->orderByDesc('created_at');
                }
            });

            $products = $query->paginate($perPage);

            // 4. Construct the Final Response
            $data = [
                'profile' => [
                    'store_name' => $user->storefront->name,
                    'bio'        => $user->storefront->bio,
                    'profile_photo' => $user->profile_photo,
                    'cover_photo' => $user->cover_photo,
                    'store-name' => $user->storefront->name,
                    'store-url'   => $user->storefront->slug,
                    // Optional: 'avatar' => $user->avatar 
                ],
                'products' => $products
            ];

        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }

        return apiSuccess('Storefront profile retrieved successfully.', $data);
    }

    public function storefrontProducts(SearchProductRequest $request) {
        try {
            // 1. Get Input Parameters
            $perPage  = $request->get('per_page', 10);
            $search   = $request->get('search');
            $sort     = $request->get('sort');
            $minPrice = $request->get('min_price');
            $maxPrice = $request->get('max_price');
            
            // Optional: Filter by specific Storefront (e.g., viewing one seller's public page)
            $storefrontId = $request->get('storefront_id'); 

            // 2. Start Query: Search ALL products
            // We use 'with' to fetch the Store and User info efficiently (Eager Loading)
            $query = Product::with(['storefront:id,user_id,name','storefront.user:id,name']);

            // --- FILTER BY SPECIFIC STORE (Optional) ---
            $query->when($storefrontId, function ($q) use ($storefrontId) {
                return $q->where('storefront_id', $storefrontId);
            });

            // --- FILTER BY PRICE ---
            $query->when($minPrice, function ($q) use ($minPrice) {
                return $q->where('price', '>=', $minPrice);
            });
            $query->when($maxPrice, function ($q) use ($maxPrice) {
                return $q->where('price', '<=', $maxPrice);
            });

            // --- SEARCH (Title, Code, OR Store Name) ---
            $query->when($search, function ($q) use ($search) {
                return $q->where(function ($subQuery) use ($search) {
                    $subQuery->where('title', 'LIKE', "%{$search}%")
                             ->orWhere('vaitor_product_code', 'LIKE', "%{$search}%")
                             // Bonus: Search by Store Name too!
                             ->orWhereHas('storefront', function($storeQ) use ($search) {
                                 $storeQ->where('name', 'LIKE', "%{$search}%");
                             });
                });
            });

            // --- SORT ---
            $query->when($sort, function ($q) use ($sort) {
                switch ($sort) {
                    case 'price_low':   return $q->orderBy('price', 'asc');
                    case 'price_high':  return $q->orderBy('price', 'desc');
                    case 'newest':      return $q->orderByDesc('created_at');
                    case 'oldest':      return $q->orderBy('created_at');
                    case 'title_asc':   return $q->orderBy('title', 'asc');
                    case 'title_desc':  return $q->orderBy('title', 'desc');
                    default:            return $q->orderByDesc('created_at');
                }
            });

            // 3. Execute Pagination
            $products = $query->paginate($perPage);

        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }

        return apiSuccess('Marketplace products retrieved successfully.', $products);
    }


}
