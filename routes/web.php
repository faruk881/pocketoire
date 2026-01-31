<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/click/{id}', [ProductController::class, 'trackAndRedirect'])->name('product.track')->middleware('throttle:10,1');

Route::get('/stripe/test/return', function () {
    return 'Stripe onboarding completed. You can close this tab.';
});

Route::get('/stripe/test/refresh', function () {
    return 'Stripe onboarding expired. Try again.';
});