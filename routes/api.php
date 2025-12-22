<?php

use App\Http\Controllers\ChapterController;
use App\Http\Controllers\VersionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/versions', [VersionController::class, 'index']);
Route::get('/books/{book}/chapters/{number}', [ChapterController::class, 'show']);
Route::get('/books/{book}/chapters/{number}/compare', [ChapterController::class, 'compare']);

Route::prefix('admin')->middleware('auth:admins')->group(function () {
    Route::get('/me', fn (Request $request) => $request->user());
    Route::apiResource('versions', VersionController::class)->except(['index', 'show']);
});

Route::get('/user', fn (Request $request) => $request->user())
    ->middleware('auth:users');
