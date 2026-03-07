<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the real client IP from Cloudflare's CF-Connecting-IP header.
 * When behind Cloudflare, $request->ip() would otherwise return the proxy IP.
 */
class CloudflareRealIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $realIp = $request->header('CF-Connecting-IP');

        if ($realIp && filter_var($realIp, FILTER_VALIDATE_IP)) {
            $request->server->set('REMOTE_ADDR', $realIp);
            $request->headers->set('X-Forwarded-For', $realIp);
        }

        return $next($request);
    }
}
