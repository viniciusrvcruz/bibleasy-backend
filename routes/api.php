<?php

use App\Http\Controllers\ChapterController;
use App\Http\Controllers\VersionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/versions', [VersionController::class, 'index']);

Route::prefix('books/{book}')->group(function () {
    Route::get('/chapters', [ChapterController::class, 'index']);
    Route::get('/chapters/{number}', [ChapterController::class, 'show']);
    Route::get('/chapters/{number}/compare', [ChapterController::class, 'compare']);
});

Route::prefix('admin')->middleware('auth:admins')->group(function () {
    Route::get('/me', fn (Request $request) => $request->user());
    Route::apiResource('versions', VersionController::class)->except(['index', 'show']);
});

Route::get('/user', fn (Request $request) => $request->user())
    ->middleware('auth:users');
