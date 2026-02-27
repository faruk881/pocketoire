<?php

namespace App\Http\Controllers;

use App\Http\Requests\EditProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Models\Product;
use App\Models\ProductClick;
use App\Models\ProductImage;
use App\Models\ViatorDestination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{

    private $baseUrl;
    private $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.viator.viator_api_base_url');
        $this->apiKey  = config('services.viator.viator_api_key');
    }

    private function extractViatorProductId(string $url): ?string
    {
        // Remove query string
        $cleanUrl = strtok($url, '?');

        // d is destination, everything after "-" is product code
        // Also support /p- URLs
        if (preg_match('/\/(?:d\d+-|p-)([^\/]+)/', $cleanUrl, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function showVaitorProductDestination() {
        // Fetch specific columns from the database
        $destinations = ViatorDestination::select(
            'destination_id', 
            'name', 
            'type', 
            'default_currency_code', 
            'time_zone'
        )->get();

        // Transform the data to match the exact JSON keys you want
        $formatted = $destinations->map(function ($dest) {
            return [
                'destinationId'       => $dest->destination_id,
                'name'                => $dest->name,
                'type'                => $dest->type,
                'defaultCurrencyCode' => $dest->default_currency_code,
                'timeZone'            => $dest->time_zone,
            ];
        });

        // Return the response
        return apiSuccess('All destination loaded',$formatted);
    

    }

    public function showViatorProduct(Request $request)
    {
        try{
            // Validate request
            $request->validate([
                'destination' => ['required','integer'],
                'per_page'    => ['sometimes', 'integer', 'min:1', 'max:50'],
                'page'        => ['sometimes', 'integer', 'min:1'],
                'keywords'    => ['sometimes', 'string'],
            ]);

            // Get Request Data
            $destination = $request->get('destination', 684);
            $perPage = $request->get('per_page', 10);
            $page = $request->get('page', 1);
            $startIndex = (($page - 1) * $perPage) + 1;
            $keywords = $request->get('keywords', null);

            // Check if there is search keywords. 
            if($keywords){
                // Get Search Products
                $response = Http::withHeaders([
                    'exp-api-key' => $this->apiKey,
                    'Accept' => 'application/json;version=2.0',
                    'Content-Type' => 'application/json',
                    'Accept-Language'=> 'en-US',
                ])->post($this->baseUrl . '/partner/search/freetext', [
                    "searchTerm" => $keywords,
                    "productFiltering" => [
                        "destination" => $destination,
                    ],
                    "searchTypes" => [
                        [
                            "searchType" => "PRODUCTS",
                            "pagination" => [
                                "start" => $startIndex,
                                "count" => $perPage,
                            ],
                        ]
                    ],
                    "currency" => "USD",
                ]);
                $getData = $response->json();

                $data['totalCount'] = $getData['products']['totalCount'];
                if($data['totalCount'] > 0){
                    $data['products'] = $getData['products']['results'];

                } else {
                    $data['products'] = [];
                }

            } else {
                // If there is no search keywords then get all products.
                $response = Http::withHeaders([
                    'exp-api-key' => $this->apiKey,
                    'Accept' => 'application/json;version=2.0',
                    'Content-Type' => 'application/json',
                    'Accept-Language'=> 'en-US',
                ])->post($this->baseUrl.'/partner/products/search', [
                    "filtering" => [
                        "destination" => $destination,
                    ],
                    "currency" => "USD",
                    "pagination" => [
                        "start" => $startIndex,
                        "count" => $perPage
                    ]
                ]);
                 $data = $response->json();

            }   
            
            // Check if response success.
            if ($response->failed()) {
                return apiError('Failed to fetch products from Viator | '.$response);
            }

            // Get Products.
            $products = $data['products'] ?? [];

            // Get Total Count.
            $totalCount = $data['totalCount'] ?? 0;

            // Filter the products and get only necessery details.
            $formattedProducts = collect($products)->map(function ($product) {
            // Collect image URLs
            $images = collect($product['images'] ?? [])
                ->map(function ($img) {
                    // Get the largest variant for this image
                    return collect($img['variants'] ?? [])
                        ->sortByDesc(fn($v) => ($v['width'] ?? 0) * ($v['height'] ?? 0))
                        ->first()['url'] ?? null;
                })
                ->filter()       // remove nulls
                ->take(5)        // take first 5 images
                ->values()       // reset keys
                ->all();

            return [
                'product_code' => $product['productCode'],
                'name'         => $product['title'],
                'price'        => $product['pricing']['summary']['fromPrice'] ?? null,
                'currency'     => 'USD',
                'images'       => $images,
                'rating'       => $product['reviews']['combinedAverageRating'] ?? 0,
                'url'          => $product['productUrl'],
            ];
        });

            // Return the data
            return response()->json([
                'current_page' => (int) $page,
                'per_page'     => (int) $perPage,
                'total_items'  => (int) $totalCount,
                'total_pages'  => (int) ceil($totalCount / $perPage),
                'data'         => $formattedProducts,
            ]);
        } catch (\Throwable $e) {
            return apiError($e->getMessage().' | '.$e->getLine());
        }
    }


    public function generateAffiliateLink(Request $request){
        // 1. Validate input
        $request->validate([
            'url' => ['required', 'url'],
        ]);

        $affiliateUrl = $this->genAffiliateLink($request->url);

        // 2. Parse URL
        
        return apiSuccess('Affiliate link generated.', [
            'affiliate_url' => $affiliateUrl,
        ]);
    }

    private function genAffiliateLink(string $url){
        $parts = parse_url($url);

        if (
            empty($parts['host']) ||
            ! str_contains($parts['host'], 'viator.com')
        ) {
            return apiError('Invalid Viator URL.', 422);
        }

        // 3. Extract query params
        parse_str($parts['query'] ?? '', $query);

        // 4. Ensure affiliate params exist
        if (empty($query['mcid']) || empty($query['pid'])) {
            return apiError(
                'Affiliate parameters missing. mcid and pid are required.',
                422
            );
        }

        // 5. Build clean affiliate URL
        $affiliateUrl = $parts['scheme'].'://'.$parts['host'].$parts['path']
                        .'?'.http_build_query([
                            'mcid' => $query['mcid'],
                            'pid'  => $query['pid'],
                        ]);

        return $affiliateUrl.'&medium=api&campaign=creator_'.auth()->user()->id;
    }
    

    public function storeProduct(StoreProductRequest $request){
        try {
    
            // Get the product link
            $productLink = $request->product_url;
            $productName = $request->product_name;
            $productDescription = $request->description;
            $albumId = $request->album_id;
            $imageUrl = $request->image_url;


            // Check if product exists
            $viatorProductCode = $this->extractViatorProductId($productLink);
            if (Product::where('viator_product_code', $viatorProductCode)->exists()) {
                return apiError('This product already exists.', 409);
            }

            // Check for valid album id
            $albumExists = auth()->user()
                ->storefront
                ->albums()
                ->where('id', $albumId)
                ->exists();

            if (! $albumExists) {
                return apiError('Invalid album selected', 403);
            }


            // Fetch Product Content (Name & Description)
            $contentResponse = Http::withHeaders([
                'exp-api-key' => $this->apiKey,
                'Accept-Language' => 'en-US',
                'Accept' => 'application/json;version=2.0',
            ])->get("{$this->baseUrl}/partner/products/{$viatorProductCode}");

            // Fetch Pricing Schedule (Lowest Price)
            $priceResponse = Http::withHeaders([
                'exp-api-key' => $this->apiKey,
                'Accept' => 'application/json;version=2.0',
            ])->get("{$this->baseUrl}/partner/availability/schedules/{$viatorProductCode}");


            // Get product details
            $content = $contentResponse->json();

            // Get product price details
            $priceData = $priceResponse->json();


            // Save details to database
            $product = Product::create([
                'user_id' => auth()->user()->id,
                'storefront_id' => auth()->user()->storefront->id,
                'album_id' => $albumId,
                // 'title' => $content['title'],
                'title' => $productName,
                // 'description' => $content['description'],
                'description' => $productDescription,
                'price'        => $priceData['summary']['fromPrice'] ?? null,
                'currency'     => $priceData['currency'] ?? 'USD',
                'product_link' => $productLink,
                'viator_product_code' => $viatorProductCode,
            ]);

            // Check if there is image

            if ($request->hasFile('image') && $request->file('image')->isValid()) {

                // Save the image to storage and get the path.
                $path = $request->file('image')->store('product_images', 'public');

                // Save the image to database
                ProductImage::create([
                    'product_id' => $product->id,
                    'image'      => $path,
                    'source'     => 'upload',
                ]);
            } else {

                // Save the image to database
                ProductImage::create([
                    'product_id' => $product->id,
                    'image' => $imageUrl,
                    'source' => 'viator',
                ]);
            }
            return apiSuccess('The product inserted successfully.', $product);
        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }

    }

    public function refreshProduct($id){
        // Get ithe product id
        $productId = $id;

        // Get the product
        $product = Product::find($productId);

        // Check if product exists
        if (!$product) {
            return apiError('Product not found.', 404);
        }

        // Check if the authenticated user is the owner of the product
        if ($product->user_id !== auth()->id()) {
            return apiError('Unauthorized to edit this product.', 403);
        }

        // Get the product link
        $productLink = $product->product_link;

        // Extract viator product code from the link
        $viatorProductCode = $this->extractViatorProductId($productLink);

        try{
            // Fetch Product Content (Name & Description)
            $contentResponse = Http::withHeaders([
                'exp-api-key' => $this->apiKey,
                'Accept-Language' => 'en-US',
                'Accept' => 'application/json;version=2.0',
            ])->get("{$this->baseUrl}/partner/products/{$viatorProductCode}");

            // Fetch Pricing Schedule (Lowest Price)
            $priceResponse = Http::withHeaders([
                'exp-api-key' => $this->apiKey,
                'Accept' => 'application/json;version=2.0',
            ])->get("{$this->baseUrl}/partner/availability/schedules/{$viatorProductCode}");
        } catch(\Throwable $e){
            return apiError($e->getMessage());
        }

        // Get product details
        $content = $contentResponse->json();
        $priceData = $priceResponse->json();
        
        // Delete old images
        foreach ($product->product_images as $image) {
            $image->delete(); // auto deletes file
        }

        $imageUrl = [];

        $imageUrl = collect($content['images'] ?? [])
            ->pluck('variants') // Get all variant arrays
            ->collapse()        // Merge them into one long list of variants
            ->sortByDesc(fn($v) => ($v['width'] ?? 0) * ($v['height'] ?? 0))
            ->first()['url'] ?? null;

        // return apiSuccess("",$imageUrl);
        
        // $imageUrls = [];
        // if (!empty($content['images'])) {
        //     // Use array_slice to get only the first 5 images
        //     $imagesToProcess = array_slice($content['images'], 0, 5);
            
        //     foreach ($imagesToProcess as $image) {
        //         // Collect high-res variant if available
        //         $imageUrls[] = $image['variants'][11]['url'] ?? $image['variants'][0]['url'];
        //     }
        // }
     

        try{
            $product->update([
                'title' => $content['title'],
                'description' => $content['description'],
                'price'        => $priceData['summary']['fromPrice'] ?? null,
                'currency'     => $priceData['currency'] ?? 'USD',
                'product_link' => $productLink,
                'viator_product_code' => $viatorProductCode,
            ]);
        } catch(\Throwable $e){
            return apiError($e->getMessage());
        }

        ProductImage::create([
            'product_id' => $product->id,
            'image' => $imageUrl,
            'source' => 'viator',
        ]);



        return apiSuccess('The product refreshed successfully.', $product);

    }

    public function editProduct(EditProductRequest $request, $id) {
        try {
            // Find the product
            $product = Product::find($id);

            // Check if product exists
            if (!$product) {
                return apiError('Product not found.', 404);
            }

            // Check if the authenticated user is the owner of the product
            if ($product->user_id !== auth()->id()) {
                return apiError('Unauthorized to edit this product.', 403);
            }
            // Get the album id
            $albumId = $request->album_id;

            // check for valid and authorized album if album id provided
            if ($request->filled('album_id')) {
                $albumExists = auth()->user()
                    ->storefront
                    ->albums()
                    ->where('id', $albumId)
                    ->exists();

                if ($albumExists) {
                    $product->update([
                        'album_id' => $request->album_id,
                    ]);
                } else {
                    return apiError('Invalid album selected', 403);
                }
            }

            // Handle file upload
            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                // Delete previous images
                foreach ($product->product_images as $image) {
                    $image->delete(); // auto deletes file
                }
                // return apiSuccess('',$product->product_images);

                // Store new image
                $path = $request->file('image')->store('product_images', 'public');

                // Save to database
                $product->product_images()->create([
                    'image' => $path,
                    'source' => 'upload',
                ]);
            }

            // Handle external URL
            elseif ($request->filled('image_url')) {
                // Delete previous images
                foreach ($product->product_images as $image) {
                    $image->delete(); // auto deletes file
                }

                // Save external image
                $product->product_images()->create([
                    'image' => $request->image_url,
                    'source' => 'viator',
                ]);
            }

            return apiSuccess('Product image updated successfully.', $product);

        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }
    }

    public function getProduct(Request $request)
    {   
        try{
            // Get the product link
            $productLink = $request->product_link;
        
            // Check if product exists
            $viatorProductCode = $this->extractViatorProductId($productLink);
            if (Product::where('viator_product_code', $viatorProductCode)->exists()) {
                return apiError('This product already exists.', 409);
            }

            // 1. Fetch Product Content (Name & Description)
            $contentResponse = Http::withHeaders([
                'exp-api-key' => $this->apiKey,
                'Accept-Language' => 'en-US',
                'Accept' => 'application/json;version=2.0',
            ])->get("{$this->baseUrl}/partner/products/{$viatorProductCode}");
            
            // Check the valid response
            if (! $contentResponse->successful()) {
                return apiError(
                    'Failed to fetch product from Viator. ',
                    $contentResponse->status()
                );
            }

            // 2. Fetch Pricing Schedule (Lowest Price)
            $priceResponse = Http::withHeaders([
                'exp-api-key' => $this->apiKey,
                'Accept' => 'application/json;version=2.0',
            ])->get("{$this->baseUrl}/partner/availability/schedules/{$viatorProductCode}");


            // Get product details
            $content = $contentResponse->json();

            // Get product price details
            $priceData = $priceResponse->json();


            // Get product URL
            $product_url  = $content['productUrl'];

            if(!$product_url) {
                return apiError('Product URL not found.', 404);
            }

            $affiliate_url = $this->genAffiliateLink($product_url);

            $imageUrls = [];

            $imageUrls = collect($content['images'] ?? [])
                ->pluck('variants') // Get all variant arrays
                ->collapse()        // Merge them into one long list of variants
                ->sortByDesc(fn($v) => ($v['width'] ?? 0) * ($v['height'] ?? 0))
                ->first()['url'] ?? null;

            // Extracting only the data you requested
            $data = [
                'product_name' => $content['title'] ?? 'N/A',
                'description'  => $content['description'] ?? 'N/A',
                // 'fromPrice' is the lowest lead price available for this product
                'price'        => $priceData['summary']['fromPrice'] ?? 'Contact for price',
                'currency'     => $priceData['currency'] ?? 'USD',
                'product_url' => $affiliate_url,
                'image_url'    => $imageUrls,
                'albums' => auth()->user()->storefront->albums,
                // 'all_images'   => $content['images'],
            ];

            return apiSuccess('Product details fetched successfully.', $data);
        } catch(\Throwable $e){
            return apiError($e->getMessage());
        }
    }

    public function trackAndRedirect(Request $request, $id)
    {
        try {
            // 1. Find Product (Fail if not exists to show 404)
            $product = Product::findOrFail($id);

            // 2. Identify Visitor
            // Check if they have the cookie. If not, generate a new UUID.
            $visitorId = $request->cookie('creator_visitor_id') ?? (string) Str::uuid();

            // 3. Duplicate Check (The "24-Hour Rule")
            // specific product + specific visitor + last 24 hours
            $hasClickedRecently = ProductClick::where('product_id', $id)
                ->where('visitor_id', $visitorId)
                ->where('created_at', '>=', now()->subHours(24))
                ->exists();

            // 4. Record Click (Only if NOT a duplicate)
            if (!$hasClickedRecently) {
                ProductClick::create([
                    'product_id' => $product->id,
                    'user_id'    => auth()->id(), // Logs ID if user is logged into Web Session
                    'visitor_id' => $visitorId,   // The consistent tracking ID
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ]);
            }

            // 5. Create the Cookie Object
            // Name: 'creator_visitor_id', Value: $visitorId, Duration: 1440 mins (24 Hours)
            $cookie = cookie('creator_visitor_id', $visitorId, 1440);

            // 6. Redirect & Attach Cookie
            // We attach ->withCookie() to ensure it saves during the redirect
            return redirect($product->product_link)->withCookie($cookie);

        } catch (\Throwable $e) {
            // SAFETY NET: If anything above fails (DB error, etc.), 
            // still redirect the user so you don't lose the sale.
            
            // Optional: Log::error($e->getMessage()); 
            
            $product = Product::find($id);
            if ($product) {
                return redirect($product->product_link);
            }
            abort(404);
        }
    }

}
