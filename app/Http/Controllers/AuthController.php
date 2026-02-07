<?php

namespace App\Http\Controllers;

use App\Services\OtpService;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Socialite;

use function PHPUnit\Framework\isEmpty;

class AuthController extends Controller
{
    public function login(LoginRequest $request, OtpService $otpService){
        try {
            //Get the user
            $user = User::where('email', $request->email)->first();

            // Generic error (prevents enumeration)
            if (! $user || ! Hash::check($request->password, $user->password)) {
                return apiError('Invalid login credentials.', 401);
            }

            // Check if the user is created using google?
            if(!is_null($user->google_id)){
                return apiError('User created using google. Please sign in using google.',403);
            }
            
            // Check if email verified.
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

            // Check users login session. if 3 session exists then log out from all session and login.
            $activeSessions = $user->tokens()->count();
            if($activeSessions >=3) {
                $user->tokens()->delete();
            }

            // Create usertoken. also save the device name
            $token = $user->createToken($request->device_name ?? 'web')->plainTextToken;

            // Return success message.
            return apiSuccess('Login successful.', [
                'user'  => $user,
                'token' => $token,
            ]);

        } catch(\Throwable $e) {
            return apiError($e->getMessage(),500);
        }
    } //End of login function

    public function logout(Request $request)
    {
        try {

            // Users can log out from active session or all session 
            // 
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
    } //End of logout function
}
