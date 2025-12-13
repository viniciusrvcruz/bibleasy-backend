<?php

use App\Http\Controllers\Admin\AdminSocialAuthController;
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
