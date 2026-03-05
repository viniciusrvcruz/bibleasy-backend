<?php

use App\Actions\Chapter\GetChapterAction;
use App\Enums\BookAbbreviationEnum;
use App\Enums\VersionTextSourceEnum;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Verse;
use App\Models\Version;
use App\Services\Chapter\DTOs\ChapterResponseDTO;
use Illuminate\Support\Facades\Cache;

describe('GetChapterAction', function () {
    it('returns ChapterResponseDTO from adapter', function () {
        $version = Version::factory()->create(['text_source' => VersionTextSourceEnum::DATABASE]);
        $book = Book::factory()->create([
            'version_id' => $version->id,
            'abbreviation' => BookAbbreviationEnum::GEN,
        ]);
        $chapter = Chapter::factory()->create(['number' => 1, 'book_id' => $book->id]);
        Verse::factory()->create(['chapter_id' => $chapter->id, 'number' => 1, 'text' => 'Verse 1.']);

        Cache::forget("versions:{$version->id}:books:gen:chapters:1");

        $action = app(GetChapterAction::class);
        $result = $action->execute(1, BookAbbreviationEnum::GEN, $version);

        expect($result)->toBeInstanceOf(ChapterResponseDTO::class)
            ->and($result->number)->toBe(1)
            ->and($result->verses)->toHaveCount(1);
    });

    it('caches result and returns same DTO on second call', function () {
        $version = Version::factory()->create([
            'text_source' => VersionTextSourceEnum::DATABASE,
            'cache_ttl' => 3600,
        ]);
        $book = Book::factory()->create([
            'version_id' => $version->id,
            'abbreviation' => BookAbbreviationEnum::GEN,
        ]);
        $chapter = Chapter::factory()->create(['number' => 1, 'book_id' => $book->id]);
        Verse::factory()->create(['chapter_id' => $chapter->id, 'number' => 1, 'text' => 'Cached.']);

        $action = app(GetChapterAction::class);
        $first = $action->execute(1, BookAbbreviationEnum::GEN, $version);
        $second = $action->execute(1, BookAbbreviationEnum::GEN, $version);

        expect($first->verses->first()->text)->toBe('Cached.')
            ->and($second->verses->first()->text)->toBe('Cached.');
    });

    it('uses cache key with version id and book and chapter number', function () {
        $version = Version::factory()->create([
            'cache_ttl' => 60,
            'text_source' => VersionTextSourceEnum::DATABASE,
        ]);
        $book = Book::factory()->create([
            'version_id' => $version->id,
            'abbreviation' => BookAbbreviationEnum::PSA,
        ]);
        Chapter::factory()->create(['number' => 119, 'book_id' => $book->id]);

        $key = "versions:{$version->id}:books:psa:chapters:119";
        Cache::forget($key);
        expect(Cache::has($key))->toBeFalse();

        app(GetChapterAction::class)->execute(119, BookAbbreviationEnum::PSA, $version);

        expect(Cache::has($key))->toBeTrue();
    });
});
