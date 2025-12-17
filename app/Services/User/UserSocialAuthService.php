<?php

namespace App\Services\User;

use App\Enums\Auth\UserAuthProvider;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;

class UserSocialAuthService
{
    public function redirect(UserAuthProvider $provider)
    {
        $redirectUrl = route('user.auth.callback', ['provider' => $provider->value]);

        return Socialite::driver($provider->value)
            ->redirectUrl($redirectUrl)
            ->redirect();
    }

    public function callback(UserAuthProvider $provider): string
    {
        $socialiteUser = Socialite::driver($provider->value)->user();

        $user = User::firstOrCreate(
            ['email' => $socialiteUser->getEmail()],
            ['name' => $socialiteUser->getName()]
        );

        $token = $user->createToken('user-token')->plainTextToken;

        return $token;
    }
}
