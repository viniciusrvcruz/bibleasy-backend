<?php

namespace App\Services\Version\Importers;

use App\Enums\BookAbbreviationEnum;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Verse;
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
            }
        }
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
