<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\RegisterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Default Route
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Auth routes
Route::post('/auth/register',[RegisterController::class,'register']);
Route::post('/auth/login',[AuthController::class,'login']);
Route::get('/auth/logout',[AuthController::class,'logout'])->middleware('auth:sanctum');

// Mail verify route
Route::post('/auth/email/verify',[EmailVerificationController::class,'confirmEmail']);
Route::post('/auth/email/verify/resend-otp',[EmailVerificationController::class,'mailVerifyResendOtp']);



