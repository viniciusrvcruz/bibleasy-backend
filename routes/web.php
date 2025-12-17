<?php

use App\Http\Controllers\Auth\AdminSocialAuthController;
use App\Http\Controllers\Auth\UserSocialAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('auth/admin')->group(function () {
    Route::get('/redirect/{provider}', [AdminSocialAuthController::class, 'redirect'])
        ->name('admin.auth.redirect');
    
    Route::get('/callback/{provider}', [AdminSocialAuthController::class, 'callback'])
        ->name('admin.auth.callback');
});

Route::prefix('auth/user')->group(function () {
    Route::get('/redirect/{provider}', [UserSocialAuthController::class, 'redirect'])
        ->name('user.auth.redirect');
    
    Route::get('/callback/{provider}', [UserSocialAuthController::class, 'callback'])
        ->name('user.auth.callback');
});
