<?php

namespace App\Services\Version\Importers;

use App\Enums\BookAbbreviationEnum;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Verse;
use App\Services\Version\DTOs\VersionDTO;
use Illuminate\Support\Facades\DB;

class VersionImporter
{
    public function import(VersionDTO $dto, int $versionId): void
    {
        DB::transaction(function () use ($dto, $versionId) {
            $books = Book::where('version_id', $versionId)->get()->keyBy(function ($book) {
                return $book->abbreviation->value;
            });
            $globalPosition = 1;

            foreach ($dto->books as $index => $bookDTO) {
                $abbreviation = BookAbbreviationEnum::cases()[$index];
                
                $book = $books->get($abbreviation->value);
                
                if (!$book) {
                    $book = Book::create([
                        'version_id' => $versionId,
                        'name' => $bookDTO->name,
                        'abbreviation' => $abbreviation,
                        'order' => $index,
                    ]);
                }

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
        });
    }
}
