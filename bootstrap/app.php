<?php

use App\Exceptions\CustomException;
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
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e) {

            return response()->json([
                'type' => $e instanceof CustomException ? $e->getErrorType() : basename(get_class($e)),
                'message' => $e->getMessage() ?: 'An unexpected error occurred',
                'timestamp' => now()->toISOString(),
                'errors' => is_callable([$e, 'errors']) ? $e->errors() : null,
                // Include debug info only in non-production environments
                'debug' => !app()->isProduction() ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ] : null
            ], $e->getCode() ?: 500);
        });
    })->create();
