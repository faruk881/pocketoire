<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Default Route
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Auth routes
Route::post('/auth/register',[AuthController::class,'register']);
Route::post('/auth/login',[AuthController::class,'login']);
Route::get('/auth/logout',[AuthController::class,'logout'])->middleware('auth:sanctum');

// Mail verify route
Route::post('/auth/email/verify',[AuthController::class,'confirmEmail']);
Route::post('/auth/email/verify/resend-otp',[AuthController::class,'mailVerifyResendOtp']);



