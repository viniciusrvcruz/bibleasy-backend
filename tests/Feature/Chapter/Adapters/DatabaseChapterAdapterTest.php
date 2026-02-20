<?php

use App\Enums\BookAbbreviationEnum;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Verse;
use App\Models\VerseReference;
use App\Models\Version;
use App\Services\Chapter\Adapters\DatabaseChapterAdapter;

describe('DatabaseChapterAdapter', function () {
    it('returns ChapterResponseDTO with verses and references from database', function () {
        $version = Version::factory()->create();
        $book = Book::factory()->create([
            'version_id' => $version->id,
            'abbreviation' => BookAbbreviationEnum::GEN,
            'name' => 'Gênesis',
        ]);
        $chapter = Chapter::factory()->create(['number' => 1, 'book_id' => $book->id]);
        $verse = Verse::factory()->create([
            'chapter_id' => $chapter->id,
            'number' => 1,
            'text' => 'No princípio criou Deus os céus e a terra.{{1}}',
        ]);
        VerseReference::create(['verse_id' => $verse->id, 'slug' => '1', 'text' => 'Ou "céus".']);

        $adapter = app(DatabaseChapterAdapter::class);
        $dto = $adapter->getChapter($version, BookAbbreviationEnum::GEN, 1);

        expect($dto->number)->toBe(1)
            ->and($dto->bookName)->toBe('Gênesis')
            ->and($dto->bookAbbreviation)->toBe(BookAbbreviationEnum::GEN)
            ->and($dto->verses)->toHaveCount(1)
            ->and($dto->verses->first()->number)->toBe(1)
            ->and($dto->verses->first()->text)->toContain('{{1}}')
            ->and($dto->verses->first()->titles)->toHaveCount(0)
            ->and($dto->verses->first()->references)->toHaveCount(1)
            ->and($dto->verses->first()->references->first()->slug)->toBe('1')
            ->and($dto->verses->first()->references->first()->text)->toBe('Ou "céus".');
    });

    it('returns empty titles for database source', function () {
        $version = Version::factory()->create();
        $book = Book::factory()->create(['version_id' => $version->id, 'abbreviation' => BookAbbreviationEnum::PSA]);
        $chapter = Chapter::factory()->create(['book_id' => $book->id]);
        Verse::factory()->create(['chapter_id' => $chapter->id, 'number' => 1, 'text' => 'Texto.']);

        $adapter = app(DatabaseChapterAdapter::class);
        $dto = $adapter->getChapter($version, BookAbbreviationEnum::PSA, $chapter->number);

        expect($dto->verses->first()->titles)->toHaveCount(0);
    });
});
