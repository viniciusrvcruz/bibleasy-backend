<?php

namespace Database\Factories;

use App\Enums\BookNameEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => BookNameEnum::GEN->value,
            'order' => 1,
        ];
    }
}
