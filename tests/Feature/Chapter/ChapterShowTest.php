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
        ]);
        Verse::factory()->count(3)->create(['chapter_id' => $chapter->id]);

        $response = $this->getJson("/api/versions/{$version->id}/books/{$book->abbreviation->value}/chapters/1");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id', 'number', 'book', 'verses' => [['id', 'number', 'text']]
        ]);
    });

    it('returns 404 when chapter does not exist', function () {
        $version = Version::factory()->create();
        $book = BookAbbreviationEnum::GEN;

        $response = $this->getJson("/api/versions/{$version->id}/books/gen/chapters/999");

        $response->assertStatus(404);
    });
});
