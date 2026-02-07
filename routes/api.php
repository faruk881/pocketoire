<?php

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\commissionController;
use App\Http\Controllers\AdminDashboardStatsController;
use App\Http\Controllers\AdminProductModerationController;
use App\Http\Controllers\CreatorDashboardHomeController;
use App\Http\Controllers\CreatorEarningController;
use App\Http\Controllers\CreatorProfileController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\HomePage\ContactMessageController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\StripeConnectController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\ViatorDestinationsController;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::post('/auth/register',[RegisterController::class,'register'])->name('auth.register');

Route::post('/auth/login',[AuthController::class,'login'])->name('auth.login');
Route::post('/auth/logout',[AuthController::class,'logout'])->name('auth.logout')->middleware('auth:sanctum');

Route::get('/auth/google/url', [SocialAuthController::class, 'loginUrl']);
Route::get('/auth/google/callback', [socialAuthController::class, 'callback']);


// Public Routes
Route::post('/contact-messages',[ContactMessageController::class,'store'])->name('contact.store');
Route::get('/faq',[SettingsController::class,'getFaq'])->name('faq.get');
Route::get('/terms',[SettingsController::class,'getTerms'])->name('terms.get');
Route::get('/privacy-policy',[SettingsController::class,'getPrivacyPolicy'])->name('privacy-policy.get');
// Route::get('/product/{id}',[ProductController::class,'show'])->name('product.show');
Route::get('/storefronts',[StorefrontController::class,'getStorefronts'])->name('storefronts.get');
Route::get('/products',[StorefrontController::class,'storefrontProducts'])->name('storefront.products.get');
Route::get('/products/featured',[StorefrontController::class,'storefrontFeaturedProducts'])->name('storefront.featured.products.get');
Route::get('/products/{id}',[StorefrontController::class,'storefrontSingleProduct'])->name('storefront.single.products.get');
Route::get('/storefront/{id}/profile',[StorefrontController::class,'storefrontPublicProfile']);

// Stripe Webhook Route
Route::post('/webhooks/stripe',[StripeWebhookController::class,'handle']);

// Tracking Route (Must be GET so it works as a clickable link)// * This route is in the webroute.
// Route::get('/click/{id}', [ProductController::class, 'trackAndRedirect'])->name('product.track')->middleware('throttle:10,1');



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
    Route::get('/product/get',[ProductController::class,'getProduct'])->name('product.get.product');
    
    Route::post('/product',[ProductController::class,'storeProduct'])->name('product.store')->middleware('storefrontActive');
    Route::patch('/product/{id}/refresh',[ProductController::class,'refreshProduct'])->name('product.refresh')->middleware('storefrontActive');
    Route::get('/all-viator-products',[ProductController::class,'showViatorProduct'])->name('product.showVaitorProduct');
    Route::get('/vaitor-products-destination',[ProductController::class,'showVaitorProductDestination'])->name('product.showVaitorProductDestination');
    Route::post('/generate-affiliate-link',[ProductController::class,'generateAffiliateLink'])->name('product.generateAffiliateLink');
    Route::post('/generate-link',[ProductController::class,'generateAffiliateLink'])->name('product.generateLink');
    Route::get('/creator/home',[CreatorDashboardHomeController::class,'home'])->name('creator.home');

    // payment Earnings
    Route::get('/creator/earnings',[CreatorEarningController::class,'getCreatorEarnings']);
    Route::get('/creator/earnings/payouts',[CreatorEarningController::class,'getCreatorPayouts']);
    Route::post('/creator/payouts',[CreatorEarningController::class,'storePayoutRequest']);

    // Payment Payouts
    Route::post('/stripe/connect/onboard',[StripeConnectController::class,'stripeOnboard'])->name('creator.stripe.onboard');

});

// Admin Routes
Route::middleware(['auth:sanctum','admin'])->group(function(){
    Route::get('/admin/dashboard-stats',[AdminDashboardStatsController::class,'index'])->name('admin.dashboard.stats');
    Route::get('/admin/dashboard-reports',[AdminDashboardStatsController::class,'reports'])->name('admin.dashboard.reports');

    // Settings Management
    Route::post('/admin/terms',[SettingsController::class,'storeTerms'])->name('admin.terms.store');
    Route::post('/admin/faq',[SettingsController::class,'storeFaq'])->name('admin.faq.store');
    Route::post('/admin/privacy-policy',[SettingsController::class,'storePrivacyPolicy'])->name('admin.privacy-policy.store');

    // Creator Management
    Route::get('/admin/creators',[UsersController::class,'getUsers'])->defaults('role','creator')->name('admin.creators.list');
    Route::get('/admin/creators/{id}',[UsersController::class,'getProfile'])->defaults('role','creator')->name('admin.creator.show');
    Route::patch('/admin/creator/{id}/status',[UsersController::class,'updateUserStatus'])->defaults('role','creator')->name('admin.creator.update-status');
    Route::delete('/admin/user/{id}/delete',[UsersController::class,'deleteProfile'])->name('admin.creator.delete');
    
    
    Route::patch('/admin/storefront/{id}/status',[UsersController::class,'updateCreatorStorefrontStatus'])->name('admin.creator.update-storefront-status');
    Route::post('/admin/creator/add-commission',[commissionController::class,'addCreatorcommission']);
    Route::get('/admin/creator/view-commission',[commissionController::class,'viewCreatorcommission']);

    // Payouts Management
    Route::get('/admin/payouts',[commissionController::class,'payoutView'])->name('admin.payouts.list');
    Route::patch('/admin/payouts/{id}',[commissionController::class,'updatePayoutStatus'])->name('admin.payouts.update-status');
    Route::post('/admin/global/commission/update',[commissionController::class,'updateGlobalCommission'])->name('admin.global.commission.update');
    Route::post('/admin/custom/commission/create',[commissionController::class,'createCustomCommission'])->name('admin.custom.commission.create');
    Route::put('/admin/custom/commission/{id}/update',[commissionController::class,'updateCustomCommission'])->name('admin.custom.commission.update');
    Route::get('/admin/custom/commission/add-view',[commissionController::class,'addCustomCommissionView'])->name('admin.custom.commission.add-view');
    
    // Buyer Management
    Route::get('/admin/buyers',[UsersController::class,'getUsers'])->defaults('role','buyer')->name('admin.buyers.list');
    Route::patch('/admin/buyer/{id}/status',[UsersController::class,'updateUserStatus'])->defaults('role','buyer')->name('admin.buyer.update-status');

    // Content
    Route::get('/admin/products',[AdminProductModerationController::class,'getProducts']);
    Route::get('/admin/products/{id}',[AdminProductModerationController::class,'viewProduct']);
    Route::patch('/admin/products/{id}',[AdminProductModerationController::class,'updateProductStatus']);
    Route::delete('/admin/products/{id}',[AdminProductModerationController::class,'deleteProduct']);

    // Viator Destination
    Route::get('/admin/refresh-viator-destination',[ViatorDestinationsController::class,'refreshDestinations']);


});




