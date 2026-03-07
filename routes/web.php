<?php

use App\Http\Controllers\Auth\AdminSocialAuthController;
use App\Http\Controllers\Auth\UserSocialAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Bible API',
    ]);
});

Route::get('/ip', function (Illuminate\Http\Request $request) {
    return [
        'ip' => $request->ip(),
        'forwarded' => $request->header('x-forwarded-for'),
        'cf' => $request->header('cf-connecting-ip'),
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
    ];
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
