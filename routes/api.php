<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\SocialAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Default Route
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Auth routes
Route::post('/auth/register',[RegisterController::class,'register']);
Route::post('/auth/login',[AuthController::class,'login']);
Route::post('/auth/logout',[AuthController::class,'logout'])->middleware('auth:sanctum');
Route::post('/auth/google',[SocialAuthController::class,'googleAuth']);

// Password reset routes
Route::post('/auth/forgot-password',[PasswordController::class,'forgotPassword']);
Route::post('/auth/forgot-password/verify-otp',[PasswordController::class,'verifyPasswordResetOtp']);  

// Mail verify route
Route::post('/auth/email/verify-otp',[EmailVerificationController::class,'verifyOtp']);
Route::post('/auth/email/verify/resend-otp',[EmailVerificationController::class,'resendOtp']);





