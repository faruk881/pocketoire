<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\PasswordResetRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Models\User;
use App\Services\OtpService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordController extends Controller
{
    public function forgotPassword(ForgotPasswordRequest $request, OtpService $otpService) {

        try {
            // Get the user
            $user = User::where('email', $request->email)->first();

            // Check if user exists
            if (! $user) {
                return apiSuccess('If the email exists, a verification code has been sent.');
            }

            // Check if user is created using google id.
            if($user->google_id){
                return apiError('Password reset is not available for Google-authenticated accounts.',422);
            }

            // Check if user email is verified.
            // if (! $user->email_verified_at) {
            //     return apiError('You cannot request password reset until you verify email first');
            // }

            // OTP expired or not generated yet → resend
            if (! $user->otp_expires_at || Carbon::now()->gt($user->otp_expires_at)) {
                $otpService->sendEmailOtp($user);
            }
            return apiSuccess(
                'A verification code has been sent to your email.',['email' => $user->email],
                200
            );
            

            // OTP already valid → block resend
            // return apiError(
            //     'A verification code was already sent. Please try again after it expires.',
            //     429
            // );
        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }
    }

    public function updatePassword(UpdatePasswordRequest $request) {
        try {
            // Get the user
            $user = User::where('email', $request->email)
                ->where('password_reset_token', hash('sha256', $request->password_reset_token))
                ->where('password_reset_expires_at', '>', now())
                ->first();

            // Check if user exists
            if (! $user) {
                return apiError('Invalid or expired reset token', 422);
            }
            
            if(Hash::check($request->password, $user->password)){
                return apiError('New password cannot be the same as the current password',422);
            }

            // Update the user's password
            $user->update([
                'password' => $request->password,
                'password_reset_token' => null,
                'password_reset_expires_at' => null,
                'otp_verified_at' => null,
            ]);

            // revoke all tokens
            $user->tokens()->delete();

            // return the message
            return apiSuccess('Password reset successful');
        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }

    }

    public function verifyPasswordResetOtp(PasswordResetRequest $request){
        try {

            // Get the user.
            $user = User::where('email', $request->email)->first();

            // Check if user exists and otp valid.
            if (
                ! $user ||
                ! $user->otp ||
                ! Hash::check($request->otp, $user->otp) ||
                now()->gt($user->otp_expires_at)
            ) {
                return apiError('Invalid or expired OTP', 422);
            }

            // Generate password reset table
            $plainToken = Str::random(64);

            // Update the database
            $user->update([
                $user->email_verified_at ? null : ['email_verified_at' => now()],
                'password_reset_token' => hash('sha256', $plainToken),
                'password_reset_expires_at' => now()->addMinutes(10),
                'otp_verified_at' => Carbon::now(),
                'otp' => null,
                'otp_expires_at' => null,
            ]);

            // revoke all tokens
            $user->tokens()->delete();

            $data['email'] = $user->email;
            $data['password_reset_token'] = $plainToken;
            
            return apiSuccess('OTP Verified Successfully',$data);
        } catch(\Throwable $e) {
            return apiError($e->getMessage());
        }
    }

    // public function forgotPassword(){

    // }
    public function changePassword(ChangePasswordRequest $request){
        try{
            $user = auth()->user();

            // Check current password
            if(!Hash::check($request->current_password, $user->password)){
                return apiError('Current password is incorrect',422);
            }

            if(Hash::check($request->new_password, $user->password)){
                return apiError('New password cannot be the same as the current password',422);
            }

            // Update to new password
            $user->password = $request->new_password;
            $user->save();
            $session = request()->user()->currentAccessToken();
            // Revoke current token
            $session->delete();

            return apiSuccess('Password changed successfully');
        } catch(\Throwable $e){
            return apiError($e->getMessage());
        }
    }
}
