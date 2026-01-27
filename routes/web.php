<?php

use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/storefront/products/{id}',[StorefrontController::class,'storefrontSingleProduct'])->name('storefront.single.products.get');