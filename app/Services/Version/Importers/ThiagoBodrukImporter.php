<?php

namespace App\Services\Version\Importers;

use App\Enums\BookNameEnum;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Verse;
use App\Services\Version\Interfaces\VersionImporterInterface;
use App\Exceptions\Version\VersionImportException;

/**
 * Special thanks to Thiago Bodruk for providing multiple Bible versions
 * in JSON format. You can access the repository here:
 * https://github.com/thiagobodruk/bible/tree/master/json
 */
class ThiagoBodrukImporter implements VersionImporterInterface
{
    public function parse(string $content): array
    {
        $bom = pack('H*','EFBBBF');
        $contentText = preg_replace("/^$bom/", '', $content);
        $data = json_decode($contentText, true, 512, JSON_THROW_ON_ERROR);

        if(!is_array($data)) {
            throw new VersionImportException('invalid_format', 'JSON must be an array of books');
        }

        return $data;
    }

    public function validate(array $data): void
    {
        if(count($data) !== 66) {
            throw new VersionImportException('invalid_books_count', 'Expected 66 books but got ' . count($data));
        }

        foreach ($data as $index => $book) {
            $chapters = $book['chapters'] ?? null;
            $bookName = BookNameEnum::cases()[$index] ?? null;

            if (empty($chapters) || !is_array($chapters)) {
                throw new VersionImportException('missing_chapters', "Book '{$bookName->value}' is missing 'chapters'");
            }

            foreach ($chapters as $chapterIndex => $verses) {
                $chapterNumber = $chapterIndex + 1;

                if(!is_array($verses)) {
                    throw new VersionImportException('invalid_chapter_format', "Chapter {$chapterNumber} in the book '{$bookName->value}' must be an array of verses.");
                }

                foreach ($verses as $verseIndex => $verse) {
                    $verseNumber = $verseIndex + 1;

                    if(!is_string($verse)) {
                        throw new VersionImportException('invalid_verse_format', "Verse {$verseNumber} in chapter {$chapterNumber} of the book '{$bookName->value}' must be a string.");
                    }
                }
            }
        }
    }

    public function import(array $data, int $versionId): void
    {
        $books = Book::all();

        foreach ($data as $index => $bookData) {
            $book = $books->firstWhere('name', BookNameEnum::cases()[$index]->value);

            foreach ($bookData['chapters'] as $chapterIndex => $chapterVerses) {
                $chapterNumber = $chapterIndex + 1;

                $chapter = Chapter::create([
                    'number' => $chapterNumber,
                    'book_id' => $book->id,
                    'version_id' => $versionId,
                ]);

                $verses = collect($chapterVerses)->map(fn($verse, $verseIndex) => [
                    'chapter_id' => $chapter->id,
                    'number' => $verseIndex + 1,
                    'text' => $verse,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->toArray();

                Verse::insert($verses);
            }
        }
    }
}
