<?php

namespace Database\Seeders;

use App\Enums\BookNameEnum;
use App\Models\Book;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    public function run(): void
    {
        $books = collect(BookNameEnum::cases())
            ->map(fn(BookNameEnum $bookName, $index) => [
                'name' => $bookName->value,
                'order' => $index
            ])
            ->toArray();

        Book::upsert(
            $books,
            ['name'],
            ['order']
        );
    }
}
