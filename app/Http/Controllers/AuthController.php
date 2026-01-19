<?php

namespace App\Http\Controllers;

use App\Services\OtpService;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request, OtpService $otpService){
        try {
            $user = User::where('email', $request->email)->first();

            // Generic error (prevents enumeration)
            if (! $user || ! Hash::check($request->password, $user->password)) {
                return apiError('Invalid login credentials.', 401);
            }

            if($user->google_id){
                return apiError('User created using google. Please sign in using google.',403);
            }
            
            // Email not verified
            if (! $user->email_verified_at) {

                if ( ! $user->otp_expires_at || Carbon::now()->gt($user->otp_expires_at)) {
                    $otpService->sendEmailOtp($user);
                    return apiError('error with mail sending',403);
                }

                return apiError(
                    'Your email address is not verified. A verification code has been sent.',
                    403
                );
            }
            // Account status check
            if ($user->status !== 'active') {
                return apiError('Your account is not active.', 403);
            }

            $token = $user->createToken($request->device_name ?? 'web')->plainTextToken;

            return apiSuccess('Login successful.', [
                'user'  => new AuthUserResource($user),
                'token' => $token,
            ]);

        } catch(\Throwable $e) {
            return apiError($e->getMessage(),500);
        }
    }

    public function logout(Request $request)
    {
        try {
            if ($request->type == 'all'){
                $request->user()->tokens()->delete();
                $message = "Logged out from all session.";
            } else {
                $request->user()->currentAccessToken()->delete();
                $message = "Logged out from current session.";
            }
            return apiSuccess($message);
        } catch (\Throwable $e) {
            return apiError(
                app()->isLocal() ? $e->getMessage() : 'Logout failed.',
                500
            );
        }
    }
}
