<?php

namespace Database\Factories;

use App\Enums\Admin\AdminAuthProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Admin>
 */
class AdminFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'auth_provider' => AdminAuthProvider::GOOGLE->value,
        ];
    }
}
