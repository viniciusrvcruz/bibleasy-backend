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
 * Adapter for YouVersion JSON format
 * Expects a single JSON file with book structure (no verse text, as text comes from external API)
 */
class JsonYouVersionAdapter implements VersionAdapterInterface
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

        $booksData = $data['books'] ?? null;

        if (!is_array($booksData) || empty($booksData)) {
            throw new VersionImportException('missing_books', "JSON must contain a 'books' array");
        }

        $books = collect($booksData)->map(function ($book, $index) {
            return $this->parseBook($book, $index);
        });

        return new VersionDTO($books);
    }

    private function parseBook(array $book, int $index): BookDTO
    {
        $bookId = $book['id'] ?? null;
        $bookTitle = $book['title'] ?? null;
        $bookChapters = $book['chapters'] ?? null;

        if (!$bookId) {
            throw new VersionImportException('missing_book_id', "Book at index {$index} must have an 'id' attribute");
        }

        if (!$bookTitle) {
            throw new VersionImportException('missing_book_title', "Book at index {$index} must have a 'title' attribute");
        }

        if (!is_array($bookChapters) || empty($bookChapters)) {
            throw new VersionImportException('missing_chapters', "Book '{$bookTitle}' must have a 'chapters' array");
        }

        $abbreviation = $this->resolveBookAbbreviation($bookId, $index);

        $chapters = collect($bookChapters)->map(fn ($chapter, $chapterIndex) => 
            $this->parseChapter($chapter, $chapterIndex, $bookTitle)
        );

        return new BookDTO($bookTitle, $abbreviation, $chapters);
    }

    /**
     * Resolve YouVersion book ID to BookAbbreviationEnum
     */
    private function resolveBookAbbreviation(string $bookId, int $index): BookAbbreviationEnum
    {
        $normalized = strtolower($bookId);

        return BookAbbreviationEnum::tryFrom($normalized) ?? throw new VersionImportException(
            'invalid_book_id',
            "Book ID '{$bookId}' at index {$index} does not match any known book abbreviation"
        );
    }

    private function parseChapter(array $chapter, int $chapterIndex, string $bookTitle): ChapterDTO
    {
        $chapterId = $chapter['id'] ?? null;

        if ($chapterId === null) {
            throw new VersionImportException(
                'missing_chapter_id',
                "Chapter at index {$chapterIndex} of book '{$bookTitle}' must have an 'id' attribute"
            );
        }

        $chapterVerses = $chapter['verses'] ?? null;

        if (!is_array($chapterVerses) || empty($chapterVerses)) {
            throw new VersionImportException(
                'missing_verses',
                "Chapter {$chapterId} of book '{$bookTitle}' must have a 'verses' array"
            );
        }

        $verses = collect($chapterVerses)->map(fn ($verse, $verseIndex) => 
            $this->parseVerse($verse, $verseIndex, (int) $chapterId, $bookTitle)
        );

        return new ChapterDTO((int) $chapterId, $verses);
    }

    /**
     * Parse verse from YouVersion format (no text content, only structure)
     */
    private function parseVerse(array $verse, int $verseIndex, int $chapterId, string $bookTitle): VerseDTO
    {
        $verseId = $verse['id'] ?? null;

        if ($verseId === null) {
            throw new VersionImportException(
                'missing_verse_id',
                "Verse at index {$verseIndex} in chapter {$chapterId} of book '{$bookTitle}' must have an 'id' attribute"
            );
        }

        // YouVersion adapter creates verses with empty text (text comes from external API)
        return new VerseDTO((int) $verseId, '');
    }
}
