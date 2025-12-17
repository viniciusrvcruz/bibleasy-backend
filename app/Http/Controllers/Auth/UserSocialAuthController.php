<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Auth\UserAuthProvider;
use App\Http\Controllers\Controller;
use App\Services\User\UserSocialAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class UserSocialAuthController extends Controller
{
    public function __construct(
        private readonly UserSocialAuthService $authService
    ) {}

    public function redirect(UserAuthProvider $provider): RedirectResponse
    {
        return $this->authService->redirect($provider);
    }

    public function callback(UserAuthProvider $provider): JsonResponse
    {
        $token = $this->authService->callback($provider);

        return response()->json([
            'token' => $token,
        ]);
    }
}
