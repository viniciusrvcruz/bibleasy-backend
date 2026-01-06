<?php

use App\Enums\BookAbbreviationEnum;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Verse;
use App\Models\Version;

describe('Chapter Show', function () {
    it('returns a chapter with verses', function () {
        $version = Version::factory()->create();
        $book = Book::factory()->create([
            'version_id' => $version->id,
            'abbreviation' => BookAbbreviationEnum::GEN,
            'name' => BookAbbreviationEnum::GEN->value,
        ]);
        $chapter = Chapter::factory()->create([
            'number' => 1,
            'book_id' => $book->id,
            'position' => 1,
        ]);
        Verse::factory()->count(3)->create(['chapter_id' => $chapter->id]);

        $response = $this->getJson("/api/books/{$book->abbreviation->value}/chapters/1?version_id={$version->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id', 'number', 'position', 'book', 'verses' => [['id', 'number', 'text']]
        ]);
    });

    it('returns 404 when chapter does not exist', function () {
        $version = Version::factory()->create();
        $book = BookAbbreviationEnum::GEN;

        $response = $this->getJson("/api/books/gen/chapters/999?version_id={$version->id}");

        $response->assertStatus(404);
    });

    it('includes previous and next chapters', function () {
        $version = Version::factory()->create();
        $book = Book::factory()->create([
            'version_id' => $version->id,
            'abbreviation' => BookAbbreviationEnum::GEN,
            'name' => BookAbbreviationEnum::GEN->value,
        ]);
        
        Chapter::factory()->create(['number' => 1, 'book_id' => $book->id, 'position' => 1]);
        Chapter::factory()->create(['number' => 2, 'book_id' => $book->id, 'position' => 2]);
        Chapter::factory()->create(['number' => 3, 'book_id' => $book->id, 'position' => 3]);

        $response = $this->getJson("/api/books/{$book->abbreviation->value}/chapters/2?version_id={$version->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['previous', 'next']);
    });
});
