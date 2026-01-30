<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCreatorProfileRequest;
use App\Http\Resources\CreatorProfileResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CreatorProfileController extends Controller
{
    public function show(){
        $creator = auth()->user()->load('storefront');

        return apiSuccess('User loaded.',[
            'data' => new CreatorProfileResource($creator)
        ]);
    }

    public function update(UpdateCreatorProfileRequest $request){
        $user = auth()->user();

        $data = $request->validated();
        // Update user fields
        if(isset($data['name'])){
            $user->name = $data['name'];
        }
        $user->save();

        // Update storefront fields
        $storefront = $user->storefront;
        if($storefront){
            if(isset($data['store_name'])){
                $storefront->name = $data['store_name'];
            }
            if(isset($data['store_bio'])){
                $storefront->bio = $data['store_bio'];
            }
            if(isset($data['storefront_url'])){
                $storefront->slug = $data['storefront_url'];
            }
            if(isset($data['tiktok_link'])){
                $storefront->tiktok_link = $data['tiktok_link'];
            }
            if(isset($data['instagram_link'])){
                $storefront->instagram_link = $data['instagram_link'];
            }
            if(isset($data['profile_photo'])){
            // Delete old profile photo if exists
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            // Store new photo
            $path = $data['profile_photo']->store(
                'storefronts/profile_photos',
                'public'
            );

            // Save new path
            $user->profile_photo = $path; 
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
            'data' => new CreatorProfileResource($user->load('storefront'))
        ]);

    }
}
