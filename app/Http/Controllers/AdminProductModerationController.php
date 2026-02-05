<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Auth\Events\Validated;
use Illuminate\Http\Request;

class AdminProductModerationController extends Controller
{
    public function getProducts(Request $request) {
        try{

            // Get requests
            $perPage = $request->get('per_page', 10);
            $search  = $request->get('search');
            $status  = $request->get('status'); 

            // Fetch product
            $products = Product::with([
                    'storefront:id,name',
                    'user:id,name',
                    'first_image'
                ])
                ->select('id','user_id','storefront_id','title','status','product_link')
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                    });
                })
                ->when($status, function ($query) use ($status) {
                    $query->where('status', $status);
                })
                ->paginate($perPage);

            return apiSuccess('Products retrieved successfully.', $products);

        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }

    }

    public function UpdateProductStatus(Request $request, $id)
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
}
