<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Auth\Events\Validated;
use Illuminate\Http\Request;

class AdminProductModerationController extends Controller
{
    public function getProducts(Request $request) {
        $perPage = $request->get('per_page', 10);
        $search  = $request->get('search');
        $status  = $request->get('status'); // active | flagged

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
    }

    public function getUpdateProductStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected,flagged,pending',
        ]);

        $status = $validated['status'];

        $product = Product::find($id);

        if (!$product) {
            return apiError('Product not found.', 404);
        }

        if($product->status == $status) {
            return apiError('The product already has the specified status.', 409);
        }
        if($status === 'pending') {
            return apiError('You cannot put product in pending status', 409);
        }

        $product->update([
            'status' => $request->status,
        ]);

        return apiSuccess('Product status updated successfully.', $product->status);
    }
}
