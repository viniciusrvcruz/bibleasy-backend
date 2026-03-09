<?php

namespace App\Support;

use App\Models\Version;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Centralizes chapter rate limit configuration and block key logic.
 */
class ChapterRateLimit
{
    public const MAX_ATTEMPTS_PER_MINUTE = 60;

    public const BLOCK_DURATION_SECONDS = 3600; // 1 hour

    public const API_KEY_HEADER = 'X-Api-Key';

    /**
     * Check if the request should bypass rate limiting (X-App-Key header must match config key).
     */
    public static function shouldBypassRateLimit(Request $request): bool
    {
        $configuredKey = config('app.api_key');
        $headerValue = $request->header(self::API_KEY_HEADER);

        if (empty($headerValue)) {
            return false;
        }

        return hash_equals($configuredKey, $headerValue);
    }

    /**
     * Extract version ID from route parameter (handles both Model and scalar).
     */
    public static function getVersionId(Request $request): int|string
    {
        $version = $request->route('version');
        
        return $version instanceof Version ? $version->id : $version;
    }

    /**
     * Generate the cache key for the block (IP + version scope).
     */
    public static function getBlockKey(string $ip, int|string $versionId): string
    {
        return "chapter-blocked:{$ip}:{$versionId}";
    }

    /**
     * Store the block in cache with expiry timestamp for accurate Retry-After.
     */
    public static function setBlock(string $blockKey): void
    {
        $expiresAt = now()->addSeconds(self::BLOCK_DURATION_SECONDS)->timestamp;

        Cache::put($blockKey, $expiresAt, self::BLOCK_DURATION_SECONDS);
    }

    /**
     * Check if the client is currently blocked.
     */
    public static function isBlocked(string $blockKey): bool
    {
        return Cache::has($blockKey);
    }

    /**
     * Register the chapter rate limiter with Laravel's RateLimiter.
     */
    public static function register(): void
    {
        RateLimiter::for('chapter', function (Request $request) {
            if (self::shouldBypassRateLimit($request)) {
                return Limit::none();
            }

            $versionId = self::getVersionId($request);
            $ip = $request->ip();

            $key = $ip . '|' . $versionId;

            return Limit::perMinute(self::MAX_ATTEMPTS_PER_MINUTE)
                ->by($key)
                ->response(function () use ($ip, $versionId): JsonResponse {
                    $blockKey = self::getBlockKey($ip, $versionId);
                    self::setBlock($blockKey);

                    Log::warning('Chapter rate limit exceeded: client blocked', [
                        'ip' => $ip,
                        'version_id' => $versionId,
                    ]);

                    return new JsonResponse([
                        'message' => 'Too many requests. Please try again later.',
                    ], Response::HTTP_TOO_MANY_REQUESTS);
                });
        });
    }
}
