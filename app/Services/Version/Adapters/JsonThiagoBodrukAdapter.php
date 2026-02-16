<?php

namespace App\Services\Version\Adapters;

use App\Enums\BookAbbreviationEnum;
use App\Services\Version\DTOs\VersionDTO;
use App\Services\Version\DTOs\BookDTO;
use App\Services\Version\DTOs\ChapterDTO;
use App\Services\Version\DTOs\VerseDTO;
use App\Services\Version\Interfaces\VersionAdapterInterface;
use App\Exceptions\Version\VersionImportException;
use App\Utils\JsonDecode;

/**
 * Adapter for Thiago Bodruk's JSON format
 * https://github.com/thiagobodruk/bible/tree/master/json
 */
class JsonThiagoBodrukAdapter implements VersionAdapterInterface
{
    public function adapt(array $files): VersionDTO
    {
        if (empty($files)) {
            throw new VersionImportException('no_files', 'At least one file is required');
        }

        $firstFile = $files[0];

        if (strtolower($firstFile->extension) !== 'json') {
            throw new VersionImportException('invalid_file_extension', 'File must have .json extension');
        }

        $data = JsonDecode::toArray($firstFile->content);

        if ($data === null) {
            throw new VersionImportException('invalid_format', 'Invalid JSON format');
        }

        $books = collect($data)->map(function ($book, $bookIndex) {
            $bookName = $book['name'] ?? null;
            $bookChapters = $book['chapters'] ?? null;

            if (!$bookName) {
                throw new VersionImportException(
                    'missing_book_name',
                    "Book {$bookIndex} must have a 'name' attribute"
                );
            }

            if (!is_array($bookChapters)) {
                throw new VersionImportException(
                    'invalid_book_format',
                    "Book {$bookIndex} must be an array of chapters"
                );
            }

            return $this->parseBook($bookChapters, $bookName, $bookIndex);
        });

        return new VersionDTO($books);
    }

    private function parseBook(array $bookChapters, string $bookName, int $index): BookDTO
    {
        $abbreviation = BookAbbreviationEnum::cases()[$index];

        $chapters = collect($bookChapters)->map(function ($verses, $chapterIndex) use ($bookName) {
            if (!is_array($verses)) {
                throw new VersionImportException(
                    'invalid_chapter_format',
                    "Chapter {$chapterIndex} of book '{$bookName}' must be an array of verses"
                );
            }

            return $this->parseChapter($verses, $chapterIndex, $bookName);
        });

        return new BookDTO($bookName, $abbreviation, $chapters);
    }

    private function parseChapter(array $verses, int $chapterIndex, string $bookName): ChapterDTO
    {
        $verses = collect($verses)->map(function ($text, $verseIndex) use ($chapterIndex, $bookName) {
            if(!is_string($text)) {
                throw new VersionImportException(
                    'invalid_verse_format',
                    "Verse {$verseIndex} in chapter {$chapterIndex} of book '{$bookName}' must be a string"
                );
            }

            return $this->parseVerse($text, $verseIndex);
        });

        return new ChapterDTO($chapterIndex + 1, $verses);
    }

    private function parseVerse(string $text, int $verseIndex): VerseDTO
    {
        return new VerseDTO($verseIndex + 1, $text);
    }
}

