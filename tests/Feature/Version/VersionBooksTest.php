<?php

use App\Enums\BookAbbreviationEnum;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Verse;
use App\Models\Version;

describe('Version Books', function () {
    it('returns all books of a version with chapters and verses_count', function () {
        $version = Version::factory()->create();
        
        $book1 = Book::factory()->create([
            'version_id' => $version->id,
            'abbreviation' => BookAbbreviationEnum::GEN,
            'name' => BookAbbreviationEnum::GEN->value,
            'order' => 1,
        ]);
        
        $book2 = Book::factory()->create([
            'version_id' => $version->id,
            'abbreviation' => BookAbbreviationEnum::EXO,
            'name' => BookAbbreviationEnum::EXO->value,
            'order' => 2,
        ]);

        $chapter1 = Chapter::factory()->create([
            'number' => 1,
            'book_id' => $book1->id,
        ]);
        foreach (range(1, 31) as $num) {
            Verse::factory()->create(['chapter_id' => $chapter1->id, 'number' => $num]);
        }

        $chapter2 = Chapter::factory()->create([
            'number' => 2,
            'book_id' => $book1->id,
        ]);
        foreach (range(1, 25) as $num) {
            Verse::factory()->create(['chapter_id' => $chapter2->id, 'number' => $num]);
        }

        $chapter3 = Chapter::factory()->create([
            'number' => 1,
            'book_id' => $book2->id,
        ]);
        foreach (range(1, 22) as $num) {
            Verse::factory()->create(['chapter_id' => $chapter3->id, 'number' => $num]);
        }

        $response = $this->getJson("/api/versions/{$version->id}/books");

        $response->assertStatus(200);
        $response->assertJsonCount(2);
        $response->assertJsonStructure([
            [
                'id',
                'name',
                'order',
                'chapters' => [
                    ['id', 'number', 'verses_count']
                ]
            ]
        ]);
        
        // Verifica que os books estão ordenados por order
        $response->assertJsonPath('0.order', 1);
        $response->assertJsonPath('1.order', 2);
        
        // Verifica que os chapters estão ordenados por number
        $response->assertJsonPath('0.chapters.0.number', 1);
        $response->assertJsonPath('0.chapters.1.number', 2);
        
        // Verifica o verses_count
        $response->assertJsonPath('0.chapters.0.verses_count', 31);
        $response->assertJsonPath('0.chapters.1.verses_count', 25);
        $response->assertJsonPath('1.chapters.0.verses_count', 22);
    });

    it('returns books ordered by order field', function () {
        $version = Version::factory()->create();
        
        $book3 = Book::factory()->create([
            'version_id' => $version->id,
            'abbreviation' => BookAbbreviationEnum::LEV,
            'name' => BookAbbreviationEnum::LEV->value,
            'order' => 3,
        ]);
        
        $book1 = Book::factory()->create([
            'version_id' => $version->id,
            'abbreviation' => BookAbbreviationEnum::GEN,
            'name' => BookAbbreviationEnum::GEN->value,
            'order' => 1,
        ]);
        
        $book2 = Book::factory()->create([
            'version_id' => $version->id,
            'abbreviation' => BookAbbreviationEnum::EXO,
            'name' => BookAbbreviationEnum::EXO->value,
            'order' => 2,
        ]);

        $response = $this->getJson("/api/versions/{$version->id}/books");

        $response->assertStatus(200);
        $response->assertJsonCount(3);
        $response->assertJsonPath('0.order', 1);
        $response->assertJsonPath('1.order', 2);
        $response->assertJsonPath('2.order', 3);
    });

    it('returns chapters ordered by number', function () {
        $version = Version::factory()->create();
        $book = Book::factory()->create([
            'version_id' => $version->id,
        ]);
        
        Chapter::factory()->create(['number' => 3, 'book_id' => $book->id]);
        Chapter::factory()->create(['number' => 1, 'book_id' => $book->id]);
        Chapter::factory()->create(['number' => 2, 'book_id' => $book->id]);

        $response = $this->getJson("/api/versions/{$version->id}/books");

        $response->assertStatus(200);
        $response->assertJsonPath('0.chapters.0.number', 1);
        $response->assertJsonPath('0.chapters.1.number', 2);
        $response->assertJsonPath('0.chapters.2.number', 3);
    });

    it('returns only books for the specified version', function () {
        $version1 = Version::factory()->create();
        $version2 = Version::factory()->create();
        
        Book::factory()->create([
            'version_id' => $version1->id,
            'abbreviation' => BookAbbreviationEnum::GEN,
            'name' => BookAbbreviationEnum::GEN->value,
        ]);
        Book::factory()->create([
            'version_id' => $version1->id,
            'abbreviation' => BookAbbreviationEnum::EXO,
            'name' => BookAbbreviationEnum::EXO->value,
        ]);
        
        Book::factory()->create([
            'version_id' => $version2->id,
            'abbreviation' => BookAbbreviationEnum::GEN,
            'name' => BookAbbreviationEnum::GEN->value,
        ]);
        Book::factory()->create([
            'version_id' => $version2->id,
            'abbreviation' => BookAbbreviationEnum::EXO,
            'name' => BookAbbreviationEnum::EXO->value,
        ]);
        Book::factory()->create([
            'version_id' => $version2->id,
            'abbreviation' => BookAbbreviationEnum::LEV,
            'name' => BookAbbreviationEnum::LEV->value,
        ]);

        $response = $this->getJson("/api/versions/{$version1->id}/books");

        $response->assertStatus(200);
        $response->assertJsonCount(2);
    });

    it('returns empty array when version has no books', function () {
        $version = Version::factory()->create();

        $response = $this->getJson("/api/versions/{$version->id}/books");

        $response->assertStatus(200);
        $response->assertJsonCount(0);
    });

    it('returns 404 when version does not exist', function () {
        $response = $this->getJson('/api/versions/999/books');

        $response->assertStatus(404);
    });

    it('returns chapters with verses_count of zero when chapter has no verses', function () {
        $version = Version::factory()->create();
        $book = Book::factory()->create([
            'version_id' => $version->id,
        ]);
        
        $chapter = Chapter::factory()->create([
            'number' => 1,
            'book_id' => $book->id,
        ]);

        $response = $this->getJson("/api/versions/{$version->id}/books");

        $response->assertStatus(200);
        $response->assertJsonPath('0.chapters.0.verses_count', 0);
    });

    it('returns books with empty chapters array when book has no chapters', function () {
        $version = Version::factory()->create();
        $book = Book::factory()->create([
            'version_id' => $version->id,
        ]);

        $response = $this->getJson("/api/versions/{$version->id}/books");

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.chapters', []);
    });
});

