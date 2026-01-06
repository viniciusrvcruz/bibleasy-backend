<?php

namespace Database\Seeders;

use App\Enums\BookAbbreviationEnum;
use App\Models\Book;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    public function run(): void
    {
        // Note: Books are now created per version during import
        // This seeder is kept for backward compatibility but may not be needed
        // Books should be created via VersionImporter
    }
}
