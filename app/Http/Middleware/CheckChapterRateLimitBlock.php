<?php

namespace App\Http\Middleware;

use App\Support\ChapterRateLimit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks if the client (IP + Bible version) is blocked due to rate limit exceeded.
 *
 * IMPORTANT: This middleware MUST run BEFORE throttle:chapter. The throttle middleware
 * applies the 60 req/min limit; this middleware returns 429 immediately for clients
 * already in the 1-hour block period, avoiding unnecessary rate limit checks.
 */
class CheckChapterRateLimitBlock
{
    public function handle(Request $request, Closure $next): Response
    {
        if (ChapterRateLimit::shouldBypassRateLimit($request)) {
            return $next($request);
        }

        $versionId = $request->route('version');
        $ip = $request->ip();
        $blockKey = ChapterRateLimit::getBlockKey($ip, $versionId);

        if (ChapterRateLimit::isBlocked($blockKey)) {
            Log::warning('Chapter rate limit: blocked request', [
                'ip' => $ip,
                'version_id' => $versionId,
            ]);

            return response()->json([
                'message' => 'Too many requests. Please try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        return $next($request);
    }
}
