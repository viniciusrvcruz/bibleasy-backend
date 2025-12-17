<?php

use App\Enums\Auth\UserAuthProvider;
use App\Models\Admin;
use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;

dataset('userAuthProviders', fn () => array_map(
    fn ($provider) => $provider->value,
    UserAuthProvider::cases()
));

describe('User Social Authentication', function () {
    describe('Redirect to OAuth Provider', function () {
        it('redirects user login route to correct OAuth URL', function (string $provider) {
            $response = $this->get("/auth/user/redirect/{$provider}");

            $response->assertStatus(302);

            $redirectUrl = $response->getTargetUrl();
            $parsedQuery = [];
            parse_str(parse_url($redirectUrl)['query'] ?? '', $parsedQuery);

            $expectedBaseUrls = [
                UserAuthProvider::GOOGLE->value => 'https://accounts.google.com/o/oauth2/auth',
            ];

            expect($redirectUrl)->toStartWith($expectedBaseUrls[$provider]);
            expect($parsedQuery)->toHaveKeys([
                'client_id',
                'redirect_uri',
                'scope',
                'response_type',
                'state',
            ]);
        })->with('userAuthProviders');

        it('returns 404 if the passed provider is invalid', function () {
            $response = $this->get('/auth/user/redirect/invalid_provider');

            $response->assertNotFound();
        });
    });

    describe('OAuth Callback', function () {
        it('returns token when user exists and OAuth succeeds', function (string $provider) {
            $user = User::factory()->create([
                'email' => 'user@example.com',
            ]);

            $mockUser = Mockery::mock('Laravel\Socialite\Two\User');
            $mockUser->shouldReceive('getEmail')->andReturn($user->email);
            $mockUser->shouldReceive('getName')->andReturn($user->name);

            $mockProvider = Mockery::mock(Provider::class);
            $mockProvider->shouldReceive('user')->andReturn($mockUser);

            Socialite::shouldReceive('driver')
                ->with($provider)
                ->andReturn($mockProvider);

            $response = $this->get("/auth/user/callback/{$provider}");

            $response->assertStatus(200);
            $response->assertJsonStructure(['token']);
            expect($response->json('token'))->toBeString();
        })->with('userAuthProviders');

        it('creates user when user does not exist', function (string $provider) {
            $mockUser = Mockery::mock('Laravel\Socialite\Two\User');
            $mockUser->shouldReceive('getEmail')->andReturn('newuser@example.com');
            $mockUser->shouldReceive('getName')->andReturn('New User');

            $mockProvider = Mockery::mock(Provider::class);
            $mockProvider->shouldReceive('user')->andReturn($mockUser);

            Socialite::shouldReceive('driver')
                ->with($provider)
                ->andReturn($mockProvider);

            $response = $this->get("/auth/user/callback/{$provider}");

            $response->assertStatus(200);
            $response->assertJsonStructure(['token']);
            expect($response->json('token'))->toBeString();

            $this->assertDatabaseHas('users', [
                'email' => 'newuser@example.com',
                'name' => 'New User',
            ]);
        })->with('userAuthProviders');

        it('returns 404 if the passed provider is invalid', function () {
            $response = $this->get('/auth/user/callback/invalid_provider');

            $response->assertNotFound();
        });
    });

    describe('User Protected Routes', function () {
        it('returns user data when authenticated with valid token', function () {
            $user = User::factory()->create([
                'email' => 'user@example.com',
            ]);

            $token = $user->createToken('user-token')->plainTextToken;

            $response = $this->getJson('/api/user', [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertStatus(200);
            $response->assertJson([
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ]);
        });

        it('returns 401 when not authenticated', function () {
            $response = $this->getJson('/api/user');

            $response->assertStatus(401);
        });

        it('returns 401 when token is invalid', function () {
            $response = $this->getJson('/api/user', [
                'Authorization' => 'Bearer invalid-token',
            ]);

            $response->assertStatus(401);
        });

        it('returns 401 when token belongs to deleted user', function () {
            $user = User::factory()->create([
                'email' => 'user@example.com',
            ]);

            $token = $user->createToken('user-token')->plainTextToken;
            $user->delete();

            $response = $this->getJson('/api/user', [
                'Authorization' => 'Bearer ' . $token,
            ]);

            $response->assertStatus(401);
        });
    });

    describe('Admin cannot access user routes', function () {
        it('prevents admin from accessing user route even if admin exists', function () {
            $admin = Admin::factory()->create([
                'email' => 'admin@example.com',
            ]);

            $adminToken = $admin->createToken('admin-token')->plainTextToken;

            $response = $this->getJson('/api/user', [
                'Authorization' => 'Bearer ' . $adminToken,
            ]);

            $response->assertStatus(401);
        });
    });
});
