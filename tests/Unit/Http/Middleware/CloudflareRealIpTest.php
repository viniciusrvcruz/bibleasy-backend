<?php

use App\Http\Middleware\CloudflareRealIp;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

describe('CloudflareRealIp middleware', function () {
    it('sets request IP from CF-Connecting-IP when header is present and valid', function () {
        $realIp = '203.0.113.50';
        $request = Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => '173.245.48.1',
        ]);
        $request->headers->set('CF-Connecting-IP', $realIp);

        $middleware = new CloudflareRealIp();
        $middleware->handle($request, fn () => new Response());

        expect($request->ip())->toBe($realIp);
    });

    it('keeps REMOTE_ADDR when CF-Connecting-IP header is missing', function () {
        $proxyIp = '173.245.48.1';
        $request = Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => $proxyIp,
        ]);

        $middleware = new CloudflareRealIp();
        $middleware->handle($request, fn () => new Response());

        expect($request->ip())->toBe($proxyIp);
    });

    it('keeps REMOTE_ADDR when CF-Connecting-IP is not a valid IP', function () {
        $proxyIp = '173.245.48.1';
        $request = Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => $proxyIp,
        ]);
        $request->headers->set('CF-Connecting-IP', 'not-an-ip');

        $middleware = new CloudflareRealIp();
        $middleware->handle($request, fn () => new Response());

        expect($request->ip())->toBe($proxyIp);
    });

    it('keeps REMOTE_ADDR when CF-Connecting-IP is empty', function () {
        $proxyIp = '173.245.48.1';
        $request = Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => $proxyIp,
        ]);
        $request->headers->set('CF-Connecting-IP', '');

        $middleware = new CloudflareRealIp();
        $middleware->handle($request, fn () => new Response());

        expect($request->ip())->toBe($proxyIp);
    });

    it('passes through to next and returns response', function () {
        $request = Request::create('/test', 'GET', [], [], [], [
            'REMOTE_ADDR' => '173.245.48.1',
        ]);
        $request->headers->set('CF-Connecting-IP', '203.0.113.50');

        $expectedResponse = new Response('ok', 200);
        $middleware = new CloudflareRealIp();
        $response = $middleware->handle($request, fn () => $expectedResponse);

        expect($response)->toBe($expectedResponse);
    });
});
