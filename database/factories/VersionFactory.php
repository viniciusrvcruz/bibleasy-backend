<?php

namespace Database\Factories;

use App\Enums\VersionLanguageEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Version>
 */
class VersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'abbreviation' => fake()->words(2, true),
            'name' => fake()->words(4, true),
            'language' => VersionLanguageEnum::ENGLISH->value,
            'copyright' => fake()->sentence(),
        ];
    }
}
