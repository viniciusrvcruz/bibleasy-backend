<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Admin\AdminAuthProvider;
use App\Http\Controllers\Controller;
use App\Services\Admin\AdminSocialAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class AdminSocialAuthController extends Controller
{
    public function __construct(
        private readonly AdminSocialAuthService $authService
    ) {}

    public function redirect(AdminAuthProvider $provider): RedirectResponse
    {
        return $this->authService->redirect($provider);
    }

    public function callback(AdminAuthProvider $provider): JsonResponse
    {
        $token = $this->authService->callback($provider);

        return response()->json([
            'token' => $token,
        ]);
    }
}

