<?php

use App\Enums\BookNameEnum;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Verse;
use App\Models\Version;

describe('Chapter Compare', function () {
    it('compares verses across multiple versions', function () {
        $version1 = Version::factory()->create();
        $version2 = Version::factory()->create();
        $book = Book::factory()->create(['name' => BookNameEnum::GEN->value]);

        $chapter1 = Chapter::factory()->create(['number' => 1, 'version_id' => $version1->id, 'book_id' => $book->id]);
        $chapter2 = Chapter::factory()->create(['number' => 1, 'version_id' => $version2->id, 'book_id' => $book->id]);

        Verse::factory()->create(['chapter_id' => $chapter1->id, 'number' => 1]);
        Verse::factory()->create(['chapter_id' => $chapter2->id, 'number' => 1]);

        $response = $this->getJson("/api/books/{$book->name}/chapters/1/compare?verses=1&versions={$version1->id},{$version2->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(2);
    });

    it('compares verse ranges', function () {
        $version = Version::factory()->create();
        $book = Book::factory()->create(['name' => BookNameEnum::GEN->value]);
        $chapter = Chapter::factory()->create(['number' => 1, 'version_id' => $version->id, 'book_id' => $book->id]);
        
        Verse::factory()->create(['chapter_id' => $chapter->id, 'number' => 1]);
        Verse::factory()->create(['chapter_id' => $chapter->id, 'number' => 2]);
        Verse::factory()->create(['chapter_id' => $chapter->id, 'number' => 3]);

        $response = $this->getJson("/api/books/{$book->name}/chapters/1/compare?verses=1-3&versions={$version->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(3, '0.verses');
    });
});
