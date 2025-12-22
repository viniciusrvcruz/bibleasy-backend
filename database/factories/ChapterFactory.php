<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Version;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChapterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 1,
            'position' => 1,
            'book_id' => Book::factory(),
            'version_id' => Version::factory(),
        ];
    }
}
