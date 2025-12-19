<?php

namespace App\Services\Version\Importers;

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
            $books = Book::all()->keyBy('name');
            $globalPosition = 1;

            foreach ($dto->books as $bookDTO) {
                $book = $books->get($bookDTO->name);

                foreach ($bookDTO->chapters as $chapterDTO) {
                    $chapter = Chapter::create([
                        'number' => $chapterDTO->number,
                        'position' => $globalPosition++,
                        'book_id' => $book->id,
                        'version_id' => $versionId,
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
