<?php

namespace Database\Factories;

use App\Models\Chapter;
use Illuminate\Database\Eloquent\Factories\Factory;

class VerseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'chapter_id' => Chapter::factory(),
            'number' => fake()->numberBetween(1, 176),
            'text' => fake()->sentence(),
        ];
    }
}
