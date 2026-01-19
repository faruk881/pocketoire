<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\EmailOtpNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OtpService
{
    /**
     * Send email OTP to user
     */
    public function sendEmailOtp(User $user): array
    {
        try {
            // Prevent OTP spam
            if ($user->otp_expires_at && $user->otp_expires_at->isFuture()) {
                return [
                    'success' => false,
                    'message' => 'A verification code has already been sent. Please try again later.',
                ];
            }

            $otp = random_int(100000, 999999);

            $user->forceFill([
                'otp' => Hash::make($otp),
                'otp_expires_at' => now()->addMinutes(10),
            ])->save();

            $user->notify(new EmailOtpNotification($otp));

            return [
                'success' => true,
                'message' => 'A verification code has been sent to your email address.',
            ];

        } catch (\Throwable $e) {
            Log::error('Failed to send email OTP', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'error'   => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Unable to send the verification code at this time.',
            ];
        }
    }
}
