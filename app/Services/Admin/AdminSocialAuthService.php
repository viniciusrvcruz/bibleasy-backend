<?php

namespace App\Services\Admin;

use App\Enums\Admin\AdminAuthProvider;
use App\Models\Admin;
use Laravel\Socialite\Facades\Socialite;

class AdminSocialAuthService
{
    public function redirect(AdminAuthProvider $provider)
    {
        $redirectUrl = route('admin.auth.callback', ['provider' => $provider->value]);

        return Socialite::driver($provider->value)
            ->redirectUrl($redirectUrl)
            ->redirect();
    }

    public function callback(AdminAuthProvider $provider): string
    {
        $socialiteUser = Socialite::driver($provider->value)->user();

        $admin = Admin::where('email', $socialiteUser->getEmail())
            ->where('auth_provider', $provider->value)
            ->firstOrFail();

        $token = $admin->createToken('admin-token')->plainTextToken;

        return $token;
    }
}

