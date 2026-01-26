<?php

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CreatorProfileController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\HomePage\ContactMessageController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::post('/auth/register',[RegisterController::class,'register'])->name('auth.register');
Route::post('/auth/login',[AuthController::class,'login'])->name('auth.login');
Route::post('/auth/logout',[AuthController::class,'logout'])->name('auth.logout')->middleware('auth:sanctum');
Route::post('/auth/google',[SocialAuthController::class,'googleAuth'])->name('auth.google');


// Public Routes
Route::post('/contact-messages',[ContactMessageController::class,'store'])->name('contact.store');
Route::get('/faq',[SettingsController::class,'getFaq'])->name('faq.get');
Route::get('/terms',[SettingsController::class,'getTerms'])->name('terms.get');
Route::get('/privacy-policy',[SettingsController::class,'getPrivacyPolicy'])->name('privacy-policy.get');
Route::get('/product/{id}',[ProductController::class,'show'])->name('product.show');
Route::get('/storefronts',[StorefrontController::class,'getStorefronts'])->name('storefronts.get');
Route::get('/storefront/products',[StorefrontController::class,'storefrontProducts'])->name('storefront.products.get');
Route::get('/storefront/products/{id}',[StorefrontController::class,'storefrontSingleProduct'])->name('storefront.single.products.get');



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
    Route::get('/storefront/profile',[StorefrontController::class,'storefrontProfile']);
    
});

// Creator Routes
Route::middleware(['auth:sanctum','creator'])->group(function(){
    Route::post('/storefront/create-album',[AlbumController::class,'createAlbum'])->middleware('storefrontActive');
    Route::get('/storefront/albums',[AlbumController::class,'getAlbums'])->name('storefront.albums.get');
    Route::put('/storefront/albums/{id}',[AlbumController::class,'updateAlbum'])->name('storefront.albums.update');
    Route::get('/creator/profile', [CreatorProfileController::class, 'show'])->name('creator.profile.show');
    route::patch('/creator/profile',[CreatorProfileController::class,'update'])->name('creator.profile.update');
    Route::post('/product',[ProductController::class,'store'])->name('product.store');
    Route::get('/all-vaitor-products',[ProductController::class,'showVaitorProduct'])->name('product.showVaitorProduct');
    Route::get('/vaitor-products-destination',[ProductController::class,'showVaitorProductDestination'])->name('product.showVaitorProductDestination');
    Route::post('/generate-affiliate-link',[ProductController::class,'generateAffiliateLink'])->name('product.generateAffiliateLink');
    Route::post('/generate-link',[ProductController::class,'generateAffiliateLink'])->name('product.generateLink');

});

// Admin Routes
Route::middleware(['auth:sanctum','admin'])->group(function(){

    // Settings Management
   Route::post('/admin/terms',[SettingsController::class,'storeTerms'])->name('admin.terms.store');
   Route::post('/admin/faq',[SettingsController::class,'storeFaq'])->name('admin.faq.store');
   Route::post('/admin/privacy-policy',[SettingsController::class,'storePrivacyPolicy'])->name('admin.privacy-policy.store');

    // Creator Management
   Route::get('/admin/creators',[UsersController::class,'getUsers'])->defaults('role','creator')->name('admin.creators.list');
   Route::patch('/admin/creator/{id}/status',[UsersController::class,'updateUserStatus'])->defaults('role','creator')->name('admin.creator.update-status');
   Route::patch('/admin/storefront/{id}/status',[UsersController::class,'updateCreatorStorefrontStatus'])->name('admin.creator.update-storefront-status');

   // Buyer Management
   Route::get('/admin/buyers',[UsersController::class,'getUsers'])->defaults('role','buyer')->name('admin.buyers.list');
   Route::patch('/admin/buyer/{id}/status',[UsersController::class,'updateUserStatus'])->defaults('role','buyer')->name('admin.buyer.update-status');
});




