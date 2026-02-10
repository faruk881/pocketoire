<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class UserProfileController extends Controller
{
    public function show(){
        try {
            // Get the user with saved products
            $user = User::where('id', auth()->id())
            ->select('id', 'name', 'email', 'profile_photo')
            ->with(['savedProducts' => function($query) {
                // Select product fields + the images relationship
                $query->select('products.id', 'title', 'price')
                      ->with('product_image');
            }])
            ->first();

            // Prepare the return data
            $data = [
                'user' => $user
            ];

            // Retuen message
            return apiSuccess('Profile retrieved successfully.', $data);
        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }
    }

    public function update(){
        return "worked";
    }
}
