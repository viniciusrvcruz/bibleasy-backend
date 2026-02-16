<?php

namespace App\Services\Chapter\Adapters;

use App\Enums\BookAbbreviationEnum;
use App\Models\Chapter;
use App\Models\Version;
use App\Services\Chapter\DTOs\ChapterResponseDTO;
use App\Services\Chapter\DTOs\VerseReferenceResponseDTO;
use App\Services\Chapter\DTOs\VerseResponseDTO;
use App\Services\Chapter\Interfaces\ChapterSourceAdapterInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fetches chapter content from the database and maps to ChapterResponseDTO.
 */
class DatabaseChapterAdapter implements ChapterSourceAdapterInterface
{
    public function getChapter(
        Version $version,
        BookAbbreviationEnum $abbreviation,
        int $number
    ): ChapterResponseDTO {
        $chapter = Chapter::where('number', $number)
            ->whereHas('book', fn (Builder $query) => $query
                ->where('abbreviation', $abbreviation)
                ->where('version_id', $version->id))
            ->with(['verses.references', 'book'])
            ->firstOrFail();

        $verses = $chapter->verses->map(function ($verse) {
            $references = $verse->references->map(
                fn ($ref) => new VerseReferenceResponseDTO(slug: $ref->slug, text: $ref->text)
            );

            return new VerseResponseDTO(
                number: $verse->number,
                text: $verse->text,
                titles: collect([]),
                references: $references->values()
            );
        });

        return new ChapterResponseDTO(
            number: $chapter->number,
            bookName: $chapter->book->name,
            bookAbbreviation: $chapter->book->abbreviation,
            verses: $verses->values()
        );
    }
}
