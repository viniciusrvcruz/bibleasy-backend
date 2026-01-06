<?php

use App\Enums\BookAbbreviationEnum;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Verse;
use App\Models\Version;

describe('Chapter Index', function () {
    it('returns all chapters of a book', function () {
        $version = Version::factory()->create();
        $book = Book::factory()->create([
            'version_id' => $version->id,
            'abbreviation' => BookAbbreviationEnum::GEN,
            'name' => BookAbbreviationEnum::GEN->value,
        ]);
        
        $chapter1 = Chapter::factory()->create([
            'number' => 1,
            'book_id' => $book->id,
            'position' => 1,
        ]);
        foreach (range(1, 31) as $num) {
            Verse::factory()->create(['chapter_id' => $chapter1->id, 'number' => $num]);
        }

        $chapter2 = Chapter::factory()->create([
            'number' => 2,
            'book_id' => $book->id,
            'position' => 2,
        ]);
        foreach (range(1, 25) as $num) {
            Verse::factory()->create(['chapter_id' => $chapter2->id, 'number' => $num]);
        }

        $response = $this->getJson("/api/books/{$book->abbreviation->value}/chapters?version_id={$version->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(2);
        $response->assertJsonStructure([['id', 'number', 'position']]);
    });

    it('returns chapters ordered by number', function () {
        $version = Version::factory()->create();
        $book = Book::factory()->create([
            'version_id' => $version->id,
            'abbreviation' => BookAbbreviationEnum::GEN,
            'name' => BookAbbreviationEnum::GEN->value,
        ]);
        
        Chapter::factory()->create(['number' => 3, 'book_id' => $book->id, 'position' => 3]);
        Chapter::factory()->create(['number' => 1, 'book_id' => $book->id, 'position' => 1]);
        Chapter::factory()->create(['number' => 2, 'book_id' => $book->id, 'position' => 2]);

        $response = $this->getJson("/api/books/{$book->abbreviation->value}/chapters?version_id={$version->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('0.number', 1);
        $response->assertJsonPath('1.number', 2);
        $response->assertJsonPath('2.number', 3);
    });

    it('returns only chapters for the specified version', function () {
        $version1 = Version::factory()->create();
        $version2 = Version::factory()->create();
        $book1 = Book::factory()->create([
            'version_id' => $version1->id,
            'abbreviation' => BookAbbreviationEnum::GEN,
            'name' => BookAbbreviationEnum::GEN->value,
        ]);
        $book2 = Book::factory()->create([
            'version_id' => $version2->id,
            'abbreviation' => BookAbbreviationEnum::GEN,
            'name' => BookAbbreviationEnum::GEN->value,
        ]);
        
        foreach (range(1, 3) as $i) {
            Chapter::factory()->create(['book_id' => $book1->id, 'position' => $i, 'number' => $i]);
        }
        foreach (range(1, 2) as $i) {
            Chapter::factory()->create(['book_id' => $book2->id, 'position' => $i, 'number' => $i]);
        }

        $response = $this->getJson("/api/books/{$book1->abbreviation->value}/chapters?version_id={$version1->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(3);
    });

    it('returns 404 when book does not exist', function () {
        $version = Version::factory()->create();

        $response = $this->getJson("/api/books/invalid-book/chapters?version_id={$version->id}");

        $response->assertStatus(404);
    });

    it('returns empty array when book has no chapters for version', function () {
        $version = Version::factory()->create();
        $book = Book::factory()->create([
            'version_id' => $version->id,
            'abbreviation' => BookAbbreviationEnum::GEN,
            'name' => BookAbbreviationEnum::GEN->value,
        ]);

        $response = $this->getJson("/api/books/{$book->abbreviation->value}/chapters?version_id={$version->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(0);
    });
});
