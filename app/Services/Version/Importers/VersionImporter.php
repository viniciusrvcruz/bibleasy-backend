<?php

namespace App\Services\Version\Importers;

use App\Models\Book;
use App\Models\Chapter;
use App\Models\Verse;
use App\Services\Version\DTOs\VersionDTO;

class VersionImporter
{
    public function import(VersionDTO $dto, int $versionId): void
    {
        $globalPosition = 1;

        foreach ($dto->books as $index => $bookDTO) {
            $book = Book::create([
                'version_id' => $versionId,
                'name' => $bookDTO->name,
                'abbreviation' => $bookDTO->abbreviation,
                'order' => $index,
            ]);

            foreach ($bookDTO->chapters as $chapterDTO) {
                $chapter = Chapter::create([
                    'number' => $chapterDTO->number,
                    'position' => $globalPosition++,
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
}
