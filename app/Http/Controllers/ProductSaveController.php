<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductSaveController extends Controller
{
    public function toggle($id) {
        try {
            // 1. Check if the product actually exists first
            $product = Product::find($id);
            
            if (!$product) {
                return apiError('Product not found.', 404);
            }

            // 2. Perform the toggle
            $result = auth()->user()->savedProducts()->toggle($id);

            // 3. Check the result array to see what happened
            // If the ID is in 'attached', it was saved.
            // If it's in 'detached', it was removed.
            $isSaved = count($result['attached']) > 0;

            return apiSuccess($isSaved ? 'Product saved.' : 'Product un-saved.',null);
            
        } catch (\Throwable $e) {
            return apiError($e->getMessage(), 500);
        }
    }
}
