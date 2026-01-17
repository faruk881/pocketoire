<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmMailRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\OtpResentRequest;
use App\Http\Requests\UserRegisterRequest;
use App\Http\Resources\AuthUserResource;
use App\Mail\EmailOtpMail;
use App\Models\User;
use App\Notifications\EmailOtpNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    // Reusable function to sent otp to mail
    private function sendEmailOtp(User $user): array
    {
        try {
            // Prevent OTP spam (optional but recommended)
            if ($user->otp_expires_at && $user->otp_expires_at->isFuture()) {
                return [
                    'success' => false,
                    'message' => 'An OTP has already been sent. Please try again later.',
                ];
            }

            $otp = random_int(100000, 999999);

            $user->forceFill([
                'otp' => Hash::make($otp),
                'otp_expires_at' => now()->addMinutes(10),
            ])->save();

            // Mail::to($user->email)->send(
            //     new EmailOtpMail($otp)// Mailable
            // );

            // Mail::raw("Your email verification code is {$otp}. This code will expire in 10 minutes.",function ($message) use ($user) {
            //     $message->to($user->email)
            //     ->subject('Email Verification Code');
            //     }
            // );

            $user->notify(new EmailOtpNotification($otp));

            return [
                'success' => true,
                'message' => 'A verification code has been sent to your email address.',
            ];

        } catch (\Throwable $e) {

        // The error can only be seen on log. Security best practice
            Log::error('Failed to send email OTP', [
                'user_id' => $user->id ?? null,
                'email'   => $user->email ?? null,
                'Mail error'   => $e->getMessage() 
            ]);

            return [
                'success' => false,
                'message' => 'Unable to send the verification code at this time.',
            ];
        }
    }


    public function register(UserRegisterRequest $request) {
        try {

        DB::beginTransaction();

            $fields = $request->validated();

            $fields['password'] = $fields['password']; 

            $user = User::create($fields);

            $otpResult = $this->sendEmailOtp($user);

            if (!$otpResult['success']) {
                DB::rollBack();

                return apiError(
                    'Unable to send verification code. Please try again later.',
                    500
                );
            }
            DB::commit();
            return apiSuccess(
                'User registered successfully. A verification code has been sent to your email address.',
                new AuthUserResource($user)
                );

        } catch( \Throwable $e) {
            DB::rollBack();
            Log::error('User registration failed',[
                'error' => $e->getMessage()
            ]);
            return apiError('Registration failed. Please try again later.', 500);
        }
    }

    public function confirmEmail(ConfirmMailRequest $request) {
        try{
            $user = User::where('email', $request->email)->first();
            if(!$user) {
                return apiError('Invalid email or otp');
            }

            if($user->email_verified_at){
                return apiError('The mail is already verified');
            }

            if (
                !$user || 
                !$user->otp || 
                !Hash::check($request->otp, $user->otp) || 
                Carbon::now()->gt($user->otp_expires_at)) {
                return apiError('Invalid or expired OTP, Please request a new one.');
            }   

            $user->update([
                'otp' => null,
                'otp_expires_at' => null,
                'email_verified_at' => Carbon::now(),
            ]);

            return apiSuccess('email successfully verified, now you can log in',
            new AuthUserResource($user));
        } catch(\Throwable $e) {
                return apiError(
                app()->isLocal() ? $e->getMessage() : 'Something went wrong',
                500
            );
        }
    }

    public function login(LoginRequest $request){
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
                    $mailSent = $this->sendEmailOtp($user);
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

    public function mailVerifyResendOtp(OtpResentRequest $request){
        try {
            $user = User::where('email', $request->email)->first();

            // Generic error (prevents enumeration)
            if (! $user) {
                return apiError('Invalid credentials.', 401);
            }

            if ($user->email_verified_at) {
                return apiError(
                    'This email address has already been verified.',
                    409
                );
            }

            // Account status check
            if ($user->status !== 'active') {
                return apiError('Your account is not active.', 403);
            }

            if($user->google_id){
                return apiError('User created using google. Please sign in using google.',403);
            }
            
            // Email not verified
            if (! $user->email_verified_at) {

                // OTP expired or not generated yet → resend
                if (! $user->otp_expires_at || Carbon::now()->gt($user->otp_expires_at)) {
                    $this->sendEmailOtp($user);

                    return apiSuccess(
                        'A verification code has been sent to your email.',
                        null,
                        200
                    );
                }

                // OTP already valid → block resend
                return apiError(
                    'A verification code was already sent. Please try again after it expires.',
                    429
                );
            }

        } catch(\Throwable $e) {
            return apiError($e->getMessage(),500);
        }
    }
}
