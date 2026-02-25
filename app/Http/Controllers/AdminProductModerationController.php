<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Storefront;
use App\Models\User;
use Illuminate\Auth\Events\Validated;
use Illuminate\Http\Request;

class AdminProductModerationController extends Controller
{
    public function getProducts(Request $request) {
        try{

            $perPage = $request->get('per_page', 10);
            $search  = $request->get('search');
            $status  = $request->get('status');
            $products = Product::with([
                    'storefront:id,name',
                    'user:id,name',
                    'product_image'
                ])
                ->select('id','user_id','storefront_id','title','status','description','product_link')
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                    });
                })
                ->when($request->filled('status'), function ($query) use ($status) {
                    $query->where('status', $status);   
                })
                ->paginate($perPage);

            return apiSuccess('Products retrieved successfully.', $products);

        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }

    }

    public function viewProduct($id){
        try{
            // Get the product
            // $product = Product::where('id',$id)
            $product = Product::select('id','user_id','storefront_id','album_id','title','description','price','product_link','status','created_at')
            ->with('album:id,name')
            ->with('user:id,name,profile_photo,cover_photo')
            ->with('product_image')->find($id);
            $product->original_link = $product->product_link;
            $product->product_link = route('product.track', ['id' => $product->id]);

            // Check if product exists
            if (!$product) {
                return apiError('Product not found.', 404);
            }

            // Get storefront id
            $storefront_id = Product::find($id)->storefront->id;
            $creator_id = Product::find($id)->user->id;


            // Get storefront count
            $storefront_count = Product::where('storefront_id', $storefront_id)
                    ->selectRaw('
                        COUNT(*) as total,
                        SUM(status = "approved") as approved,
                        SUM(status = "rejected") as rejected,
                        SUM(status = "flagged") as flagged,
                        SUM(status = "pending") as pending
                    ')
                    ->first();


            // Prepare data
            $data = [
                'storefront_count' => $storefront_count,
                'creator_id' => $creator_id,
                'product' => $product
            ];

            // Return the message
            return apiSuccess('Product retrieved successfully.', $data);

        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }

    }

    public function updateProductStatus(Request $request, $id)
    {
        try{
            // Get requests
            $validated = $request->validate([
                'status' => 'required|in:approved,rejected,flagged,pending',
            ]);

            // Get product status
            $status = $validated['status'];

            // Get product
            $product = Product::find($id);

            // Check if product presents
            if (!$product) {
                return apiError('Product not found.', 404);
            }

            // Check if product status matches
            if($product->status == $status) {
                return apiError('The product already has the specified status.', 409);
            }

            // Check if product status already pending
            if($status === 'pending') {
                return apiError('You cannot put product in pending status', 409);
            }

            // Update product status
            $product->update([
                'status' => $request->status,
            ]);

            // Return the message
            return apiSuccess('Product status updated successfully.', $product->status);
        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }

    }

    public function deleteProduct($id)
    {
        try{
            // Get product
            $product = Product::find($id);

            // Check if product presents
            if (!$product) {
                return apiError('Product not found.', 404);
            }
            // Delete product
            foreach ($product->product_images as $image) {
                $image->delete(); // auto deletes file
            }
            $product->delete();

            // Return the message
            return apiSuccess('Product deleted successfully.', $product);
        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }

    }
}
