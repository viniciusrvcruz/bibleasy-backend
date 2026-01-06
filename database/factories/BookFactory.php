<?php

namespace Database\Factories;

use App\Enums\BookAbbreviationEnum;
use App\Models\Version;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'version_id' => Version::factory(),
            'name' => BookAbbreviationEnum::GEN->value,
            'abbreviation' => BookAbbreviationEnum::GEN,
            'order' => 1,
        ];
    }
}
