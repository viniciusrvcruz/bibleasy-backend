<?php

use App\Enums\Admin\AdminAuthProvider;
use App\Models\Admin;
use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;

// Dataset with all enum providers
dataset('adminAuthProviders', fn () => array_map(
    fn ($provider) => $provider->value,
    AdminAuthProvider::cases()
));

describe('Admin Social Authentication', function () {
    describe('Redirect to OAuth Provider', function () {
        it('redirects admin login route to correct OAuth URL', function (string $provider) {
            $response = $this->get("/auth/admin/redirect/{$provider}");

            $response->assertStatus(302);

            $redirectUrl = $response->getTargetUrl();
            $parsedQuery = [];
            parse_str(parse_url($redirectUrl)['query'] ?? '', $parsedQuery);

            $expectedBaseUrls = [
                AdminAuthProvider::GOOGLE->value => 'https://accounts.google.com/o/oauth2/auth',
            ];

            expect($redirectUrl)->toStartWith($expectedBaseUrls[$provider]);
            expect($parsedQuery)->toHaveKeys([
                'client_id',
                'redirect_uri',
                'scope',
                'response_type',
                'state',
            ]);
        })->with('adminAuthProviders');

        it('returns 404 if the passed provider is invalid', function () {
            $response = $this->get('/auth/admin/redirect/invalid_provider');

            $response->assertNotFound();
        });
    });

    describe('OAuth Callback', function () {
        it('returns token when admin exists and OAuth succeeds', function (string $provider) {
            $admin = Admin::factory()->create([
                'email' => 'admin@example.com',
                'auth_provider' => $provider,
            ]);

            $mockUser = Mockery::mock('Laravel\Socialite\Two\User');
            $mockUser->shouldReceive('getEmail')->andReturn($admin->email);

            $mockProvider = Mockery::mock(Provider::class);
            $mockProvider->shouldReceive('user')->andReturn($mockUser);

            Socialite::shouldReceive('driver')
                ->with($provider)
                ->andReturn($mockProvider);

            $response = $this->get("/auth/admin/callback/{$provider}");

            $response->assertStatus(200);
            $response->assertJsonStructure(['token']);
            expect($response->json('token'))->toBeString();
        })->with('adminAuthProviders');

        it('returns 404 when admin does not exist', function (string $provider) {
            $mockUser = Mockery::mock('Laravel\Socialite\Two\User');
            $mockUser->shouldReceive('getEmail')->andReturn('nonexistent@example.com');

            $mockProvider = Mockery::mock(Provider::class);
            $mockProvider->shouldReceive('user')->andReturn($mockUser);

            Socialite::shouldReceive('driver')
                ->with($provider)
                ->andReturn($mockProvider);

            $response = $this->get("/auth/admin/callback/{$provider}");

            $response->assertStatus(404);
        })->with('adminAuthProviders');

        it('returns 404 when admin exists but with different provider', function (string $provider) {
            $admin = Admin::factory()->create([
                'email' => 'admin@example.com',
                'auth_provider' => 'different_provider',
            ]);

            $mockUser = Mockery::mock('Laravel\Socialite\Two\User');
            $mockUser->shouldReceive('getEmail')->andReturn($admin->email);

            $mockProvider = Mockery::mock(Provider::class);
            $mockProvider->shouldReceive('user')->andReturn($mockUser);

            Socialite::shouldReceive('driver')
                ->with($provider)
                ->andReturn($mockProvider);

            $response = $this->get("/auth/admin/callback/{$provider}");

            $response->assertStatus(404);
        })->with('adminAuthProviders');

        it('returns 404 if the passed provider is invalid', function () {
            $response = $this->get('/auth/admin/callback/invalid_provider');

            $response->assertNotFound();
        });
    });

    describe('Admin Protected Routes', function () {
        it('returns admin data when authenticated with valid token', function () {
            $admin = Admin::factory()->create([
                'email' => 'admin@example.com',
                'auth_provider' => AdminAuthProvider::GOOGLE->value,
            ]);

            $token = $admin->createToken('admin-token')->plainTextToken;

            $response = $this->getJson('/api/admin/me', [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertStatus(200);
            $response->assertJson([
                'id' => $admin->id,
                'email' => $admin->email,
                'name' => $admin->name,
            ]);
        });

        it('returns 401 when not authenticated', function () {
            $response = $this->getJson('/api/admin/me');

            $response->assertStatus(401);
        });

        it('returns 401 when token is invalid', function () {
            $response = $this->getJson('/api/admin/me', [
                'Authorization' => 'Bearer invalid-token',
            ]);

            $response->assertStatus(401);
        });

        it('returns 401 when token belongs to deleted admin', function () {
            $admin = Admin::factory()->create([
                'email' => 'admin@example.com',
                'auth_provider' => AdminAuthProvider::GOOGLE->value,
            ]);

            $token = $admin->createToken('admin-token')->plainTextToken;
            $admin->delete();

            $response = $this->getJson('/api/admin/me', [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertStatus(401);
        });
    });

    describe('User cannot access admin routes', function () {
        it('prevents regular user from accessing admin route even if user exists', function () {
            $user = User::factory()->create([
                'email' => 'user@example.com',
            ]);

            $userToken = $user->createToken('user-token')->plainTextToken;

            $response = $this->getJson('/api/admin/me', [
                'Authorization' => 'Bearer ' . $userToken,
            ]);

            $response->assertStatus(401);
        });
    });
});
