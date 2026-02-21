<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCreatorProfileRequest;
use App\Http\Resources\BuyerProfileResource;
use App\Http\Resources\CreatorProfileResource;
use App\Http\Resources\UserProfileResource;
use App\Models\Product;
use App\Models\Storefront;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Storage;

class UserProfileController extends Controller
{
    public function show(){
        try {
            // Get the user with saved products
            $user = User::where('id', auth()->id())
            ->select('id', 'name', 'email', 'profile_photo', 'account_type')
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

    public function getForUpdate() {
      
        $user = new BuyerProfileResource(auth()->user());
        if($user->account_type == 'creator' && $user->storefront) {
           $user = new CreatorProfileResource(auth()->user()->load('storefront')); 
        }
        return apiSuccess('users loaded',$user);
    }

    public function update(UpdateCreatorProfileRequest $request){
        try{
            
            $user = auth()->user();

            $data = $request->validated();
            // Update user fields
            if(isset($data['name'])){
                $user->name = $data['name'];
            }
            $user->save();
            if(isset($data['profile_photo'])){
                // Delete old profile photo if exists
                if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                    Storage::disk('public')->delete($user->profile_photo);
                }

                // Store new photo
                $path = $data['profile_photo']->store('storefronts/profile_photos','public');

                // Save new path
                $user->profile_photo = $path;
                $user->save(); 
            }

            // Update storefront fields

            if($user->account_type == 'creator' && $user->storefront){
                            $storefront = $user->storefront;
                if(isset($data['store_name'])){
                    $storefront->name = $data['store_name'];
                }
                if(isset($data['store_bio'])){
                    $storefront->bio = $data['store_bio'];
                }

                if(isset($data['storefront_url'])){
                    if($user->storefront->slug !== $data['storefront_url']){

                        if(Storefront::where('slug',$data['storefront_url'])->exists()) {
                            return apiError('The storefront url already taken',422);
                        }
                        $storefront->slug = $data['storefront_url'];
                    }
                    
                    
                }
                if(isset($data['tiktok_link'])){
                    $storefront->tiktok_link = $data['tiktok_link'];
                }
                if(isset($data['instagram_link'])){
                    $storefront->instagram_link = $data['instagram_link'];
                }

                if(isset($data['cover_photo'])){
                    // Delete old cover photo if exists
                    if ($user->cover_photo && Storage::disk('public')->exists($user->cover_photo)) {
                        Storage::disk('public')->delete($user->cover_photo);
                    }
                    // Handle cover photo upload
                    $path = $data['cover_photo']->store('storefronts/cover_photos', 'public');
                    $user->cover_photo = $path;
                }
                $user->save();
                $storefront->save();
            }

            return apiSuccess('Profile updated successfully.', [
                'data' => $user->account_type == 'creator'? new CreatorProfileResource($user->load('storefront')):new BuyerProfileResource($user->load('storefront'))
            ]);
            
        } catch (\Exception $e) {
            return apiError($e->getMessage().' | '.$e->getLine());
        }

    }
}
