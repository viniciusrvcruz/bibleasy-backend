<?php

namespace App\Services\Version\Importers;

use App\Enums\BookAbbreviationEnum;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Verse;
use App\Models\VerseReference;
use App\Services\Version\DTOs\BookDTO;
use App\Services\Version\DTOs\VersionDTO;
use Illuminate\Support\Collection;

class VersionImporter
{
    public function import(VersionDTO $dto, int $versionId): void
    {
        $books = $this->sortBooks($dto->books);

        foreach ($books as $index => $bookDTO) {
            $book = Book::create([
                'version_id' => $versionId,
                'name' => $bookDTO->name,
                'abbreviation' => $bookDTO->abbreviation,
                'order' => $index,
            ]);

            foreach ($bookDTO->chapters as $chapterDTO) {
                $chapter = Chapter::create([
                    'number' => $chapterDTO->number,
                    'book_id' => $book->id,
                ]);

                $verses = $chapterDTO->verses->map(fn($verseDTO) => [
                    'chapter_id' => $chapter->id,
                    'number' => $verseDTO->number,
                    'text' => $verseDTO->text,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->toArray();

                Verse::insert($verses);

                // Import references for each verse
                $this->importReferences($chapterDTO, $chapter->id);
            }
        }
    }

    /**
     * Import references for verses in a chapter
     */
    private function importReferences($chapterDTO, int $chapterId): void
    {
        // Get all verses for this chapter ordered by number
        $verses = Verse::where('chapter_id', $chapterId)
            ->orderBy('number')
            ->get()
            ->keyBy('number');

        // Filter verses that have references
        $versesWithReferences = $chapterDTO->verses->filter(fn($verseDTO) => 
            $verses->has($verseDTO->number) && $verseDTO->references->isNotEmpty()
        );

        if ($versesWithReferences->isEmpty()) return;

        $referencesToInsert = [];

        foreach ($versesWithReferences as $verseDTO) {
            $verse = $verses->get($verseDTO->number);

            foreach ($verseDTO->references as $referenceDTO) {
                $referencesToInsert[] = [
                    'verse_id' => $verse->id,
                    'slug' => $referenceDTO->slug,
                    'text' => $referenceDTO->text,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if(empty($referencesToInsert)) return;

        VerseReference::insert($referencesToInsert);
    }

    /**
     * Sort books by their position in BookAbbreviationEnum
     *
     * @param Collection<int, BookDTO> $books
     * @return Collection<int, BookDTO>
     */
    private function sortBooks(Collection $books): Collection
    {
        $enumCases = BookAbbreviationEnum::cases();

        return $books->sortBy(function (BookDTO $book) use ($enumCases) {
            $position = array_search($book->abbreviation, $enumCases, true);

            return $position;
        })->values();
    }
}
