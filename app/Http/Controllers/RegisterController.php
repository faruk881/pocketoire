<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRegisterRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class RegisterController extends Controller
{
    public function register(UserRegisterRequest $request, OtpService $otpService) {
        try {
            DB::beginTransaction();

            $fields = $request->validated();

            $fields['password'] = $fields['password']; 

            $user = User::create($fields);

            $otpResult = $otpService->sendEmailOtp($user);

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
}
