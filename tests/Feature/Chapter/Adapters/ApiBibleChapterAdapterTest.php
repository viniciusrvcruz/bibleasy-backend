<?php

use App\Enums\BookAbbreviationEnum;
use App\Enums\VersionTextSourceEnum;
use App\Exceptions\Chapter\ChapterSourceException;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Version;
use App\Services\Chapter\Adapters\ApiBibleChapterAdapter;
use App\Services\Chapter\DTOs\ChapterResponseDTO;
use Illuminate\Support\Facades\Http;

describe('ApiBibleChapterAdapter', function () {
    it('throws chapterNotFound when chapter does not exist in database before calling API', function () {
        $version = Version::factory()->create([
            'text_source' => VersionTextSourceEnum::API_BIBLE,
            'external_version_id' => 'bible-id',
        ]);
        Book::factory()->create([
            'version_id' => $version->id,
            'abbreviation' => BookAbbreviationEnum::PSA,
        ]);
        // No chapter 999 for this book

        Http::fake();

        $adapter = app(ApiBibleChapterAdapter::class);

        expect(fn () => $adapter->getChapter($version, BookAbbreviationEnum::PSA, 999))
            ->toThrow(function (ChapterSourceException $e) {
                expect($e->getErrorType())->toBe('chapter_not_found')
                    ->and($e->getMessage())->toContain('Chapter 999 not found');
            });

        Http::assertNothingSent();
    });

    it('returns ChapterResponseDTO when API response is valid', function () {
        $version = Version::factory()->create([
            'text_source' => VersionTextSourceEnum::API_BIBLE,
            'external_version_id' => 'bible-id',
        ]);
        $book = Book::factory()->create([
            'version_id' => $version->id,
            'abbreviation' => BookAbbreviationEnum::PSA,
            'name' => 'Salmos',
        ]);
        Chapter::factory()->create(['number' => 119, 'book_id' => $book->id]);

        $content = [
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'q1'],
                'items' => [
                    ['name' => 'verse', 'type' => 'tag', 'attrs' => ['number' => '1', 'style' => 'v'], 'items' => []],
                    ['text' => 'Verse text', 'type' => 'text', 'attrs' => ['verseId' => 'PSA.119.1']],
                ],
            ],
        ];

        Http::fake([
            '*' => Http::response([
                'data' => [
                    'content' => $content,
                    'bookId' => 'PSA',
                    'number' => '119',
                ],
            ], 200),
        ]);

        config(['services.api_bible.key' => 'test-key', 'services.api_bible.base_url' => 'https://rest.api.bible/v1']);

        $adapter = app(ApiBibleChapterAdapter::class);
        $dto = $adapter->getChapter($version, BookAbbreviationEnum::PSA, 119);

        expect($dto)->toBeInstanceOf(ChapterResponseDTO::class)
            ->and($dto->number)->toBe(119)
            ->and($dto->bookName)->toBe('Salmos')
            ->and($dto->verses)->toHaveCount(1)
            ->and($dto->verses->first()->text)->toBe("Verse text\n");
    });

    it('throws externalApiError when API request fails', function () {
        $version = Version::factory()->create([
            'text_source' => VersionTextSourceEnum::API_BIBLE,
            'external_version_id' => 'bible-id',
        ]);
        $book = Book::factory()->create([
            'version_id' => $version->id,
            'abbreviation' => BookAbbreviationEnum::PSA,
        ]);
        Chapter::factory()->create(['number' => 119, 'book_id' => $book->id]);

        Http::fake(['*' => Http::response(null, 500)]);

        config(['services.api_bible.key' => 'test-key', 'services.api_bible.base_url' => 'https://rest.api.bible/v1']);

        $adapter = app(ApiBibleChapterAdapter::class);

        expect(fn () => $adapter->getChapter($version, BookAbbreviationEnum::PSA, 119))
            ->toThrow(function (ChapterSourceException $e) {
                expect($e->getErrorType())->toBe('external_api_error')
                    ->and($e->getMessage())->toContain('API Bible request failed');
            });
    });

    it('throws invalidResponse when API response structure is invalid', function () {
        $version = Version::factory()->create([
            'text_source' => VersionTextSourceEnum::API_BIBLE,
            'external_version_id' => 'bible-id',
        ]);
        $book = Book::factory()->create([
            'version_id' => $version->id,
            'abbreviation' => BookAbbreviationEnum::PSA,
        ]);
        Chapter::factory()->create(['number' => 119, 'book_id' => $book->id]);

        Http::fake([
            '*' => Http::response(['data' => []], 200),
        ]);

        config(['services.api_bible.key' => 'test-key', 'services.api_bible.base_url' => 'https://rest.api.bible/v1']);

        $adapter = app(ApiBibleChapterAdapter::class);

        expect(fn () => $adapter->getChapter($version, BookAbbreviationEnum::PSA, 119))
            ->toThrow(function (ChapterSourceException $e) {
                expect($e->getErrorType())->toBe('invalid_response')
                    ->and($e->getMessage())->toBe('Invalid API Bible response structure.');
            });
    });
});
