<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateStorefrontRequest;
use App\Http\Requests\SearchProductRequest;
use App\Http\Requests\SearchStorefrontRequest;
use App\Http\Requests\StorefrontUrlCheckRequest;
use App\Http\Requests\StoreProductRequest;
use App\Models\Album;
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

            $query = Storefront::select('id', 'user_id', 'name', 'bio',)
                ->withCount(['products as total_sold' => function ($q) {
                    $q->whereIn('status', ['confirmed', 'pending_price']);
                }])
                // Count 2: Total Products (Listed)
                ->withCount('products as total_products')
                ->with('user:id,name,profile_photo,cover_photo');


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

        // // Load stripe secrets
        // \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        // // Create setup intent for frontend to save card.
        // $setupIntent = SetupIntent::create([
        //     'customer' => $user->stripe_customer_id,
        //     'payment_method_types' => ['card'],
        // ]);

        // // Put the secrets to previously created empty variable
        // $clientSecret = $setupIntent->client_secret;

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
                    'user' => $user->only(['profile_photo', 'cover_photo', 'stripe_customer_id'])
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

            // 1. Safety Check
            if (!$user->storefront) {
                return apiError('You do not have a storefront yet.',403);
            }

            // 2. Get Input Parameters
            // Use boolean() to correctly handle "true"/"1"/"on"
            $groupByAlbum = $request->boolean('group_by_album'); 
            
            $perPage  = $request->get('per_page', 10);
            $search   = $request->get('search');
            $sort     = $request->get('sort');
            $minPrice = $request->get('min_price');
            $maxPrice = $request->get('max_price');

            // 3. Prepare Common Profile Data
            $data = [
                'profile' => [
                    'store_name'    => $user->storefront->name,
                    'bio'           => $user->storefront->bio,
                    'profile_photo' => $user->profile_photo,
                    'cover_photo'   => $user->cover_photo,
                    'store_slug'    => $user->storefront->slug,
                    'instagram'     => $user->storefront->instagram_link,
                    'tiktok'        => $user->storefront->tiktok_link,
                    'total_products'=> Product::where('storefront_id', $user->storefront->id)->count(),
                ]
            ];

            // 4. Conditional Logic: Fetch ONLY what is needed
            if ($groupByAlbum) {
                // --- OPTION A: Fetch Albums (Categorized) ---
                $albums = Album::where('storefront_id', $user->storefront->id)
                    ->with(['products' => function($q) {
                        $q->latest(); // You can also apply search filters here if needed
                    }])
                    ->get();

                // Transform Links inside Albums
                $albums->each(function($album) {
                    $album->products->transform(function($product) {
                        $product->product_link = route('product.track', ['id' => $product->id]);
                        return $product;
                    });
                });

                $data['albums'] = $albums;

            } else {
                // --- OPTION B: Fetch All Products (Flat List) ---
                $query = Product::where('storefront_id', $user->storefront->id)->withCount('clicks')->withCount('sales')->withSum('sales','creator_commission');

                // Filters
                $query->when($minPrice, fn($q) => $q->where('price', '>=', $minPrice));
                $query->when($maxPrice, fn($q) => $q->where('price', '<=', $maxPrice));
                
                $query->when($search, function ($q) use ($search) {
                    return $q->where(function ($subQuery) use ($search) {
                        $subQuery->where('title', 'LIKE', "%{$search}%")
                                 ->orWhere('vaitor_product_code', 'LIKE', "%{$search}%");
                    });
                });

                // Sorting
                $query->when($sort, function ($q) use ($sort) {
                    switch ($sort) {
                        case 'price_low':  return $q->orderBy('price', 'asc');
                        case 'price_high': return $q->orderBy('price', 'desc');
                        case 'title_asc':  return $q->orderBy('title', 'asc');
                        default:           return $q->orderByDesc('created_at');
                    }
                });

                $products = $query->paginate($perPage);

                // Transform: Add Link & Ensure Clicks are visible
                $products->getCollection()->transform(function ($product) {
                    $product->product_link = route('product.track', ['id' => $product->id]);
                    
                    // The 'total_clicks' column is already in the database, 
                    // so $product->total_clicks is automatically sent to the frontend.
                    // We don't need to do anything extra here!
                    
                    return $product;
                });

                $data['products'] = $products;
            }

        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }

        return apiSuccess('Storefront profile retrieved successfully.', $data);
    }
    public function storefrontPublicProfile(SearchProductRequest $request, $id) {
        try {
            // $user = auth()->user();
            $user = Storefront::where('id',$id)->first()->user;

            // 1. Safety Check
            if (!$user->storefront) {
                return apiError('You do not have a storefront yet.',403);
            }

            // 2. Get Input Parameters
            // Use boolean() to correctly handle "true"/"1"/"on"
            $groupByAlbum = $request->boolean('group_by_album'); 
            
            $perPage  = $request->get('per_page', 10);
            $search   = $request->get('search');
            $sort     = $request->get('sort');
            $minPrice = $request->get('min_price');
            $maxPrice = $request->get('max_price');

            // 3. Prepare Common Profile Data
            $data = [
                'profile' => [
                    'store_name'    => $user->storefront->name,
                    'bio'           => $user->storefront->bio,
                    'profile_photo' => $user->profile_photo,
                    'cover_photo'   => $user->cover_photo,
                    'store_slug'    => $user->storefront->slug,
                    'instagram'     => $user->storefront->instagram_link,
                    'tiktok'        => $user->storefront->tiktok_link,
                    'total_products'=> Product::where('storefront_id', $user->storefront->id)->count(),
                ]
            ];

            // 4. Conditional Logic: Fetch ONLY what is needed
            if ($groupByAlbum) {
                // --- OPTION A: Fetch Albums (Categorized) ---
                $albums = Album::where('storefront_id', $user->storefront->id)
                    ->with(['products' => function($q) {
                        $q->latest(); // You can also apply search filters here if needed
                    }])
                    ->get();

                // Transform Links inside Albums
                $albums->each(function($album) {
                    $album->products->transform(function($product) {
                        $product->product_link = route('product.track', ['id' => $product->id]);
                        return $product;
                    });
                });

                $data['albums'] = $albums;

            } else {
                // --- OPTION B: Fetch All Products (Flat List) ---
                $query = Product::where('storefront_id', $user->storefront->id)->withCount('clicks')->withCount('sales')->withSum('sales','creator_commission');

                // Filters
                $query->when($minPrice, fn($q) => $q->where('price', '>=', $minPrice));
                $query->when($maxPrice, fn($q) => $q->where('price', '<=', $maxPrice));
                
                $query->when($search, function ($q) use ($search) {
                    return $q->where(function ($subQuery) use ($search) {
                        $subQuery->where('title', 'LIKE', "%{$search}%")
                                 ->orWhere('vaitor_product_code', 'LIKE', "%{$search}%");
                    });
                });

                // Sorting
                $query->when($sort, function ($q) use ($sort) {
                    switch ($sort) {
                        case 'price_low':  return $q->orderBy('price', 'asc');
                        case 'price_high': return $q->orderBy('price', 'desc');
                        case 'title_asc':  return $q->orderBy('title', 'asc');
                        default:           return $q->orderByDesc('created_at');
                    }
                });

                $products = $query->paginate($perPage);

                // Transform: Add Link & Ensure Clicks are visible
                $products->getCollection()->transform(function ($product) {
                    $product->product_link = route('product.track', ['id' => $product->id]);
                    
                    // The 'total_clicks' column is already in the database, 
                    // so $product->total_clicks is automatically sent to the frontend.
                    // We don't need to do anything extra here!
                    
                    return $product;
                });

                $data['products'] = $products;
            }

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

            // We transform the collection to swap the database URL with our Tracking URL
            $products->getCollection()->transform(function ($product) {
                // Generates: http://yoursite.com/api/click/4
                $product->product_link = route('product.track', ['id' => $product->id]);
                return $product;
            });

            

            $featuredStorefronts = Storefront::where('status','approved')->take(8)->get();

            $data['products'] = $products;
            $data['featured_storefronts'] = $featuredStorefronts;

        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }


        return apiSuccess('All products retrieved successfully.', $data);
    }
    
    public function storefrontFeaturedProducts(Request $request) {
        try {

            $featuredProducts = Product::latest()
            ->take(8)->get()
            ->transform(function ($product) {
                // Generates: http://yoursite.com/api/click/4
                $product->product_link = route('product.track', ['id' => $product->id]);
                return $product;
            });

        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }
        return apiSuccess('20 latest products retrieved successfully.', $featuredProducts);
    }

    public function storefrontSingleProduct($id) {
        try {
            // 1. Find the product or fail (404)
            // We use 'with' to fetch the context: Who is selling this?
            $product = Product::with([
                'storefront:id,user_id,name,bio',
                'storefront.user:id,name,profile_photo,cover_photo',
                
                // 👇 FIX: Add 'product_images.' before the column names
                'first_image:id,product_images.product_id,image,source' 
            ])->find($id);

            // 2. Custom Error if not found
            if (!$product) {
                return apiError('Product not found.', 404);
            }

            // Optional: You could load 'related products' here if you wanted
            $related = Product::where('storefront_id', $product->storefront_id)
               ->where('id', '!=', $id)
               ->limit(4)
               ->get();
            $data['product'] = $product;
            $data['related_products'] = $related;

        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }

        return apiSuccess('Product details retrieved successfully.', $data);
    }


}
