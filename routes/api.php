<?php

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CreatorProfileController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\HomePage\ContactMessageController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

// Homepage Routes
Route::post('/contact-messages',[ContactMessageController::class,'store']);
Route::get('/faq',[SettingsController::class,'getFaq'])->name('faq.get');
Route::get('/terms',[SettingsController::class,'getTerms'])->name('terms.get');
Route::get('/privacy-policy',[SettingsController::class,'getPrivacyPolicy'])->name('privacy-policy.get');

// Auth routes
Route::post('/auth/register',[RegisterController::class,'register']);
Route::post('/auth/login',[AuthController::class,'login']);
Route::post('/auth/logout',[AuthController::class,'logout'])->middleware('auth:sanctum');
Route::post('/auth/google',[SocialAuthController::class,'googleAuth']);

// Password reset routes
Route::post('/auth/forgot-password',[PasswordController::class,'forgotPassword']);
Route::post('/auth/forgot-password/verify-otp',[PasswordController::class,'verifyPasswordResetOtp']); 
Route::put('/profile/password',[PasswordController::class,'changePassword'])->middleware(['auth:sanctum', 'canChangePassword']);

// Mail verify routes
Route::post('/auth/email/verify-otp',[EmailVerificationController::class,'verifyOtp']);
Route::post('/auth/email/verify/resend-otp',[EmailVerificationController::class,'resendOtp']);


// Storefront routes
Route::middleware('auth:sanctum')->group(function(){
    Route::post('/storefront/create',[StorefrontController::class,'createStorefront']);
    Route::post('/storefront/checkurl',[StorefrontController::class,'storefrontUrlCheck']); //for ajax checking.
    
});

// Creator Routes
Route::middleware(['auth:sanctum','creator'])->group(function(){
    Route::post('/storefront/create-album',[AlbumController::class,'createAlbum'])->middleware('storefrontActive');
    Route::get('/creator/profile', [CreatorProfileController::class, 'show'])->name('creator.profile.show');
    route::patch('/creator/profile',[CreatorProfileController::class,'update'])->name('creator.profile.update');
});

// Admin Routes
Route::middleware(['auth:sanctum','admin'])->group(function(){
   Route::post('/admin/terms',[SettingsController::class,'storeTerms'])->name('admin.terms.store');
   Route::post('/admin/faq',[SettingsController::class,'storeFaq'])->name('admin.faq.store');
   Route::post('/admin/privacy-policy',[SettingsController::class,'storePrivacyPolicy'])->name('admin.privacy-policy.store');
   Route::get('/admin/creators',[UsersController::class,'getCreators'])->name('admin.creators.list');
});




