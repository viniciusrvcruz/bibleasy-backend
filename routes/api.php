<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\VersionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/versions', [VersionController::class, 'index']);

Route::prefix('versions/{version}')->group(function () {
    Route::get('/books', [BookController::class, 'index']);
    
    Route::prefix('books/{abbreviation}')->group(function () {
        Route::get('/chapters/{number}', [ChapterController::class, 'show']);
    });
});

Route::prefix('books/{abbreviation}')->group(function () {
    Route::get('/chapters/{number}/comparison', [ChapterController::class, 'comparison']);
});

Route::prefix('admin')->middleware('auth:admins')->group(function () {
    Route::get('/me', fn (Request $request) => $request->user());
    Route::apiResource('versions', VersionController::class)->except(['index', 'show']);
});

Route::get('/user', fn (Request $request) => $request->user())
    ->middleware('auth:users');
