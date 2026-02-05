<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuthUserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Socialite;

class SocialAuthController extends Controller
{
    public function loginUrl(){
        
        // Get google login url using socialite
        $url = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();

        // Return the url
        return response()->json([
            'login_url' => $url,
        ]);

    }

    public function callback(Request $request)
    {
        try {
            // Google callback
            $googleUser = Socialite::driver('google')->stateless()->user();

            // Find user by email
            $user = User::where('email', $googleUser->getEmail())->first();

            // If account exists but not linked with Google
            if ($user && !$user->google_id) {
                return apiError(
                    'The account was created using password. Please log in using password',
                    401
                );
            }

            // Create user if not exists
            if (!$user) {
                $user = User::create([
                    'name'              => $googleUser->getName(),
                    'email'             => $googleUser->getEmail(),
                    'google_id'         => $googleUser->getId(),
                    'profile_photo'     => $googleUser->getAvatar(),
                    'email_verified_at' => now(),
                    'password'          => bcrypt(Str::random(32)),
                    'account_type'      => 'buyer',
                    'status'            => 'active',
                ]);
            }

            // Block inactive users
            if ($user->status !== 'active') {
                return apiError('Your account is not allowed to log in.', 403);
            }

            // Limit active sessions to 3
            if ($user->tokens()->count() >= 3) {
                $user->tokens()->delete();
            }

            // Create Sanctum token
            $deviceName = $request->device_name ?? 'web';
            $token = $user->createToken($deviceName)->plainTextToken;

            return redirect()->away(
                config('app.frontend_url') . '/auth/google/success?token=' . $token
            );

        } catch (\Throwable $e) {
            return apiError(
                'Google authentication failed: ' . $e->getMessage(),
                401
            );
        }
    }
}
