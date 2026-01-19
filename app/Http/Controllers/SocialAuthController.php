<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuthUserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Socialite;

class SocialAuthController extends Controller
{
    public function googleAuth(Request $request){
        try{
            // Load socialite.
            $googleUser = Socialite::driver('google')->stateless()->userFromToken($request->id_token);

            // Get the user
            $user = User::where('email',$googleUser->email)->first();

            // Check if the user is created using google.
            if($user && !$user->google_id){
                return apiError('The account was created using password. Please log in using password',401);
            }

            // Return message if account created then log in? or account was created  only login.
            $message = 'Login successful';

            // Check if user exists. if now exist then create the user.
            if(!$user) {
                $user = User::create([
                'name'              => $googleUser->name,
                'email'             => $googleUser->email,
                'google_id'         => $googleUser->id,
                'profile_photo'     => $googleUser->avatar,
                'email_verified_at' => now(),
                'password'          => Str::random(24),
                'account_type'      => 'buyer',
                'status'            => 'active'
                ]);

                // User creation message.
                $message = 'User creation and login successful';
            }
            
            // Block non active user
            if($user->status !== 'active') {
                return apiError('Your account is not allowed to log in.', 403);
            }

            // Check users login session. if 3 session exists then log out from all session and login.
            $activeSessions = $user->tokens()->count();
            if($activeSessions >=3) {
                $user->tokens()->delete();
            }

            // Create usertoken. also save the device name
            $token = $user->createToken($request->device_name ?? 'web')->plainTextToken;
            
            // Return success message. the message is in variable to know id user is created and logged in or only login.
            return apiSuccess($message,[
                'user' => new AuthUserResource($user),
                'token' => $token
            ]);
        } catch (\Throwable $e) {
            return apiError('google auth error'.$e->getMessage(), 401);
        }
    } //End of googleAuth function
}
