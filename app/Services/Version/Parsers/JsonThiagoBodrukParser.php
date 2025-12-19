<?php

namespace App\Services\Version\Parsers;

use App\Enums\BookNameEnum;
use App\Services\Version\DTOs\VersionDTO;
use App\Services\Version\DTOs\BookDTO;
use App\Services\Version\DTOs\ChapterDTO;
use App\Services\Version\DTOs\VerseDTO;
use App\Services\Version\Interfaces\VersionParserInterface;
use App\Exceptions\Version\VersionImportException;

/**
 * Parser for Thiago Bodruk's JSON format
 * https://github.com/thiagobodruk/bible/tree/master/json
 */
class JsonThiagoBodrukParser implements VersionParserInterface
{
    public function parse(string $content): VersionDTO
    {
        $data = $this->decodeJson($content);

        $books = collect($data)->map(function ($book, $bookIndex) {
            $bookChapters = $book['chapters'] ?? null;

            if (!is_array($bookChapters)) {
                throw new VersionImportException(
                    'invalid_book_format',
                    "Book {$bookIndex} must be an array of chapters"
                );
            }

            return $this->parseBook($bookChapters, $bookIndex);
        });

        return new VersionDTO($books);
    }

    private function decodeJson(string $content): array
    {
        $bom = pack('H*', 'EFBBBF');
        $content = preg_replace("/^$bom/", '', $content);

        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new VersionImportException('invalid_format', 'JSON must be an array of books');
        }
    }

    private function parseBook(array $bookChapters, int $index): BookDTO
    {
        $bookName = BookNameEnum::cases()[$index]->value;

        $chapters = collect($bookChapters)->map(function ($verses, $chapterIndex) use ($bookName) {
            if (!is_array($verses)) {
                throw new VersionImportException(
                    'invalid_chapter_format',
                    "Chapter {$chapterIndex} of book '{$bookName}' must be an array of verses"
                );
            }

            return $this->parseChapter($verses, $chapterIndex, $bookName);
        });

        return new BookDTO($bookName, $chapters);
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
