<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Models\Product;
use App\Models\ProductClick;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cookie;

class ProductController extends Controller
{
    private $apiKey = '72f9caa8-a5bb-4b9f-82af-5e42feaace5d'; //viator sandbox key
    private $baseUrl = 'https://api.sandbox.viator.com/partner';
    private $urlExt = '/products/search';

    private function extractViatorProductId(string $url): ?string
    {
        // Remove query string
        $cleanUrl = strtok($url, '?');

        // Match /dXXX-{productId}
        if (preg_match('/\/d\d+-([0-9]+P[0-9]+)/', $cleanUrl, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function showVaitorProductDestination() {
            $response = Http::withHeaders([
            'exp-api-key' => $this->apiKey,
            'Accept' => 'application/json;version=2.0',
            'Accept-Language' => 'en-US',
            'Content-Type' => 'application/json',
        ])->get($this->baseUrl.'/destinations');

        return response()->json($response->json());

    }

    public function showVaitorProduct(Request $request)
    {
        $request->validate([
            'destination' => ['required','integer'],
            'per_page'    => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page'        => ['sometimes', 'integer', 'min:1'],
            'keywords'    => ['sometimes', 'string'],
        ]);

        $destination = $request->get('destination', 684);
        $perPage = $request->get('per_page', 10);
        $page = $request->get('page', 1);
        $startIndex = (($page - 1) * $perPage) + 1;


        $response = Http::withHeaders([
            'exp-api-key' => $this->apiKey,
            'Accept' => 'application/json;version=2.0',
            'Content-Type' => 'application/json',
            'Accept-Language'=> 'en-US',
        ])->post($this->baseUrl.$this->urlExt, [
            "filtering" => [
                "destination" => $destination,
            ],
            "currency" => "USD",
            "pagination" => [
                "start" => $startIndex,
                "count" => $perPage
            ]
        ]);

        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to fetch products from Viator'
            ], 500);
        }

        $data = $response->json();
        $products = $data['products'] ?? [];
        $totalCount = $data['totalCount'] ?? 0;

        $formattedProducts = collect($products)->map(function ($product) {
            $images = [];

            foreach (array_slice($product['images'] ?? [], 0, 5) as $img) {
                $variant = collect($img['variants'] ?? [])->last();
                if ($variant && isset($variant['url'])) {
                    $images[] = $variant['url'];
                }
            }

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

        return response()->json([
            'current_page' => (int) $page,
            'per_page'     => (int) $perPage,
            'total_items'  => (int) $totalCount,
            'total_pages'  => (int) ceil($totalCount / $perPage),
            'data'         => $formattedProducts,
        ]);
    }


    public function generateAffiliateLink(Request $request){
        // 1. Validate input
        $request->validate([
            'url' => ['required', 'url'],
        ]);

        // 2. Parse URL
        $parts = parse_url($request->url);

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
        $affiliateUrl =
            $parts['scheme'].'://'.$parts['host'].$parts['path']
            .'?'.http_build_query([
                'mcid' => $query['mcid'],
                'pid'  => $query['pid'],
            ]);

        return apiSuccess('Affiliate link generated.', [
            'affiliate_url' => $affiliateUrl.'&medium=api&campaign=creator_'.auth()->user()->id,
        ]);
    }
    

    public function store(StoreProductRequest $request){
        $productLink = $request->product_link;
        $viatorProductCode = $this->extractViatorProductId($productLink);
        if (Product::where('vaitor_product_code', $viatorProductCode)->exists()) {
            return apiError('This product already exists.', 409);
        }

        try{
            // 1. Fetch Product Content (Name & Description)
            $contentResponse = Http::withHeaders([
                'exp-api-key' => $this->apiKey,
                'Accept-Language' => 'en-US',
                'Accept' => 'application/json;version=2.0',
            ])->get("{$this->baseUrl}/products/{$viatorProductCode}");

            // 2. Fetch Pricing Schedule (Lowest Price)
            $priceResponse = Http::withHeaders([
                'exp-api-key' => $this->apiKey,
                'Accept' => 'application/json;version=2.0',
            ])->get("{$this->baseUrl}/availability/schedules/{$viatorProductCode}");
        } catch(\Throwable $e){
            return apiError($e->getMessage());
        }

        // if ($contentResponse->failed() || $priceResponse->failed()) {
        //     return response()->json(['error' => 'Could not fetch product data'.$productCode], 404);
        // }

        $content = $contentResponse->json();
        $priceData = $priceResponse->json();

        $imageUrls = [];
        if (!empty($content['images'])) {
            // Use array_slice to get only the first 5 images
            $imagesToProcess = array_slice($content['images'], 0, 5);
            
            foreach ($imagesToProcess as $image) {
                // Collect high-res variant if available
                $imageUrls[] = $image['variants'][11]['url'] ?? $image['variants'][0]['url'];
            }
        }

        $product = Product::create([
            'user_id' => auth()->user()->id,
            'storefront_id' => auth()->user()->storefront->id,
            'album_id' => $request->album_id,
            'title' => $content['title'],
            'description' => $content['description'],
            'price'        => $priceData['summary']['fromPrice'] ?? 'Contact for price',
            'currency'     => $priceData['currency'] ?? 'USD',
            'product_link' => $productLink,
            'vaitor_product_code' => $viatorProductCode,
        ]);

        foreach ($imageUrls as $url) {
            ProductImage::create([
                'product_id' => $product->id,
                'image' => $url,
                'source' => 'viator',
            ]);
        }

        return apiSuccess('products inserted successfully.', $product);

    }

    public function show($productCode)
    {
        try{
            // 1. Fetch Product Content (Name & Description)
            $contentResponse = Http::withHeaders([
                'exp-api-key' => $this->apiKey,
                'Accept-Language' => 'en-US',
                'Accept' => 'application/json;version=2.0',
            ])->get("{$this->baseUrl}/products/{$productCode}");

            // 2. Fetch Pricing Schedule (Lowest Price)
            $priceResponse = Http::withHeaders([
                'exp-api-key' => $this->apiKey,
                'Accept' => 'application/json;version=2.0',
            ])->get("{$this->baseUrl}/availability/schedules/{$productCode}");
        } catch(\Throwable $e){
            return apiError($e->getMessage());
        }

        // if ($contentResponse->failed() || $priceResponse->failed()) {
        //     return response()->json(['error' => 'Could not fetch product data'.$productCode], 404);
        // }

        $content = $contentResponse->json();
        $priceData = $priceResponse->json();

        $imageUrls = [];
        if (!empty($content['images'])) {
            // Use array_slice to get only the first 5 images
            $imagesToProcess = array_slice($content['images'], 0, 5);
            
            foreach ($imagesToProcess as $image) {
                // Collect high-res variant if available
                $imageUrls[] = $image['variants'][11]['url'] ?? $image['variants'][0]['url'];
            }
        }

        // Extracting only the data you requested
        return response()->json([
            'product_name' => $content['title'] ?? 'N/A',
            'description'  => $content['description'] ?? 'N/A',
            // 'fromPrice' is the lowest lead price available for this product
            'price'        => $priceData['summary']['fromPrice'] ?? 'Contact for price',
            'currency'     => $priceData['currency'] ?? 'USD',
            'image_url'    => $imageUrls,
        ]);
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
