<?php

namespace Tests;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function actAsAdmin(?string $email = null): Admin
    {
        $data = $email ? ['email' => $email] : [];

        $admin = Admin::factory()->create($data);

        Sanctum::actingAs($admin, guard: 'admins');

        return $admin;
    }

    protected function actAsUser(?string $email = null): User
    {
        $data = $email ? ['email' => $email] : [];

        $user = User::factory()->create($data);

        Sanctum::actingAs($user, guard: 'users');

        return $user;
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Session::flush();

        parent::tearDown();
    }
}
