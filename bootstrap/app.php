<?php

use App\Http\Middleware\CheckChapterRateLimitBlock;
use App\Http\Middleware\CloudflareRealIp;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // $middleware->prepend(CloudflareRealIp::class);
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'chapter.rate_limit' => CheckChapterRateLimitBlock::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
