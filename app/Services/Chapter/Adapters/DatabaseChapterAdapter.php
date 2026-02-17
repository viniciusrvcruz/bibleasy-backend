<?php

namespace App\Services\Chapter\Adapters;

use App\Enums\BookAbbreviationEnum;
use App\Models\Chapter;
use App\Models\Version;
use App\Services\Chapter\DTOs\ChapterResponseDTO;
use App\Services\Chapter\DTOs\VerseReferenceResponseDTO;
use App\Services\Chapter\DTOs\VerseResponseDTO;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Fetches chapter content from the database and maps to ChapterResponseDTO.
 * Raw DB result is cached; DTO is built on every request so mapping changes apply without cache invalidation.
 */
class DatabaseChapterAdapter extends AbstractCachedChapterAdapter
{
    /**
     * @return array{number: int, book_name: string, book_abbreviation: string, verses: array<int, array{number: int, text: string, titles: array, references: array<int, array{slug: string, text: string}>}>}
     */
    protected function fetchRawChapter(
        Version $version,
        BookAbbreviationEnum $abbreviation,
        int $number
    ): array {
        $chapter = Chapter::where('number', $number)
            ->whereHas('book', fn (Builder $query) => $query
                ->where('abbreviation', $abbreviation)
                ->where('version_id', $version->id))
            ->with(['verses.references', 'book'])
            ->firstOrFail();

        $verses = $chapter->verses->map(function ($verse) {
            $references = $verse->references->map(
                fn ($ref) => ['slug' => $ref->slug, 'text' => $ref->text]
            )->values()->all();

            return [
                'number' => $verse->number,
                'text' => $verse->text,
                'titles' => [],
                'references' => $references,
            ];
        })->values()->all();

        return [
            'number' => $chapter->number,
            'book_name' => $chapter->book->name,
            'book_abbreviation' => $chapter->book->abbreviation->value,
            'verses' => $verses,
        ];
    }

    protected function processRawToDto(
        array $raw,
        Version $version,
        BookAbbreviationEnum $abbreviation,
        int $number
    ): ChapterResponseDTO {
        $verses = collect($raw['verses'] ?? [])->map(function ($v) {
            $references = collect($v['references'] ?? [])->map(
                fn (array $ref) => new VerseReferenceResponseDTO(slug: $ref['slug'], text: $ref['text'])
            );

            return new VerseResponseDTO(
                number: $v['number'],
                text: $v['text'],
                titles: collect($v['titles'] ?? []),
                references: $references->values()
            );
        });

        return new ChapterResponseDTO(
            number: $raw['number'],
            bookName: $raw['book_name'],
            bookAbbreviation: BookAbbreviationEnum::from($raw['book_abbreviation']),
            verses: $verses->values()
        );
    }
}
