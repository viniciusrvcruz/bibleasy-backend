<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware('auth:admin')->group(function () {
    Route::get('/me', fn (Request $request) => $request->user());
});

Route::get('/user', fn (Request $request) => $request->user())
    ->middleware('auth:users');
