<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\PasswordResetRequest;
use App\Models\User;
use App\Services\OtpService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
            if (! $user->email_verified_at) {
                return apiError('You cannot request password reset until you verify email first');
            }

            // OTP expired or not generated yet → resend
            if (! $user->otp_expires_at || Carbon::now()->gt($user->otp_expires_at)) {
                $otpService->sendEmailOtp($user);

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
        } catch (\Throwable $e) {
            return apiError($e->getMessage());
        }
    }

    public function verifyPasswordResetOtp(PasswordResetRequest $request){
        try {
            $user = User::where('email', $request->email)->first();

            if (
                ! $user ||
                ! $user->otp ||
                ! Hash::check($request->otp, $user->otp) ||
                now()->gt($user->otp_expires_at)
            ) {
                return apiError('Invalid or expired OTP', 422);
            }

            $user->update([
                'password' => $request->password,
                'otp_verified_at' => Carbon::now(),
                'otp' => null,
                'otp_expires_at' => null,
            ]);

            // revoke all tokens
            $user->tokens()->delete();

            return apiSuccess('Password reset successful');
        } catch(\Throwable $e) {
            return apiError($e->getMessage());
        }
    }
}
