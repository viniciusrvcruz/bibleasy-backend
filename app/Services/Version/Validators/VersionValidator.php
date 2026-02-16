<?php

namespace App\Services\Version\Validators;

use App\Enums\VersionTextSourceEnum;
use App\Services\Version\DTOs\VersionDTO;
use App\Services\Version\DTOs\BookDTO;
use App\Services\Version\DTOs\ChapterDTO;
use App\Services\Version\DTOs\VerseDTO;
use App\Services\Version\DTOs\VerseReferenceDTO;
use App\Exceptions\Version\VersionImportException;

class VersionValidator
{
    public function validate(VersionDTO $dto, VersionTextSourceEnum $textSource): void
    {
        foreach ($dto->books as $index => $book) {
            // Validate that each book is an instance of BookDTO
            if (!$book instanceof BookDTO) {
                throw new VersionImportException('invalid_book_type', "Book at index {$index} is not an instance of BookDTO");
            }

            if ($book->chapters->isEmpty()) {
                throw new VersionImportException('missing_chapters', "Book '{$book->name}' is missing chapters");
            }

            foreach ($book->chapters as $chapter) {
                // Validate that each chapter is an instance of ChapterDTO
                if (!$chapter instanceof ChapterDTO) {
                    throw new VersionImportException('invalid_chapter_type', "Chapter in book '{$book->name}' is not an instance of ChapterDTO");
                }

                if ($chapter->verses->isEmpty()) {
                    throw new VersionImportException('missing_verses', "Chapter {$chapter->number} in book '{$book->name}' is missing verses");
                }

                foreach ($chapter->verses as $verse) {
                    // Validate that each verse is an instance of VerseDTO
                    if (!$verse instanceof VerseDTO) {
                        throw new VersionImportException('invalid_verse_type', "Verse in chapter {$chapter->number} of book '{$book->name}' is not an instance of VerseDTO");
                    }

                    $this->validateVerseText($verse, $book->name, $chapter->number, $textSource);

                    // Text content and reference validations only apply to database sources
                    if ($textSource === VersionTextSourceEnum::DATABASE) {
                        // Validate that verse text contains only text and slug placeholders
                        $this->validateVerseTextContent($verse, $book->name, $chapter->number);

                        // Validate references
                        foreach ($verse->references as $reference) {
                            if (!$reference instanceof VerseReferenceDTO) {
                                throw new VersionImportException('invalid_reference_type', "Reference in verse {$verse->number} of chapter {$chapter->number} in book '{$book->name}' is not an instance of VerseReferenceDTO");
                            }
                            $this->validateReference($reference, $verse, $book->name, $chapter->number);
                        }
                    }
                }
            }
        }
    }

    /**
     * Validate verse text based on text source
     * Database: text must not be empty
     * External API: text must be empty
     */
    private function validateVerseText(VerseDTO $verse, string $bookName, int $chapterNumber, VersionTextSourceEnum $textSource): void
    {
        $verseLocation = "Verse {$verse->number} in chapter {$chapterNumber} of book '{$bookName}'";

        if ($textSource === VersionTextSourceEnum::DATABASE && empty(trim($verse->text))) {
            throw new VersionImportException(
                'empty_verse',
                "{$verseLocation} has empty text"
            );
        }

        if ($textSource !== VersionTextSourceEnum::DATABASE && $verse->text !== '') {
            throw new VersionImportException(
                'non_empty_verse_for_external_source',
                "{$verseLocation} must have empty text for external API sources"
            );
        }
    }

    /**
     * Validate a single verse reference
     */
    private function validateReference(VerseReferenceDTO $reference, VerseDTO $verse, string $bookName, int $chapterNumber): void
    {
        // Validate that each reference is an instance of VerseReferenceDTO
        if (!$reference instanceof VerseReferenceDTO) {
            throw new VersionImportException('invalid_reference_type', "Reference in verse {$verse->number} of chapter {$chapterNumber} in book '{$bookName}' is not an instance of VerseReferenceDTO");
        }

        // Validate that reference slug is not empty
        if (empty($reference->slug)) {
            throw new VersionImportException('empty_reference_slug', "Reference in verse {$verse->number} of chapter {$chapterNumber} in book '{$bookName}' has empty slug");
        }

        // Validate that reference text is not empty
        if (empty($reference->text)) {
            throw new VersionImportException('empty_reference_text', "Reference with slug '{$reference->slug}' in verse {$verse->number} of chapter {$chapterNumber} in book '{$bookName}' has empty text");
        }

        // Validate that reference text contains only valid text (no markers or invalid characters)
        $this->validateReferenceTextContent($reference, $bookName, $chapterNumber, $verse->number);

        // Validate that reference slug exists in verse text
        $slugPlaceholder = '{{' . $reference->slug . '}}';
        if (strpos($verse->text, $slugPlaceholder) === false) {
            throw new VersionImportException('missing_slug_in_verse_text', "Reference with slug '{$reference->slug}' in verse {$verse->number} of chapter {$chapterNumber} in book '{$bookName}' is missing its placeholder '{$slugPlaceholder}' in the verse text");
        }
    }

    /**
     * Validate that verse text contains only valid text and slug placeholders
     * Text should not contain USFM markers or other invalid characters
     */
    private function validateVerseTextContent(VerseDTO $verse, string $bookName, int $chapterNumber): void
    {
        $text = $verse->text;

        // Remove all valid slug placeholders ({{slug}}) from the text
        // First, collect all valid slugs from references
        $validSlugs = $verse->references->map(fn($ref) => $ref->slug)->toArray();
        
        // Remove all valid placeholders
        $textWithoutPlaceholders = $text;
        foreach ($validSlugs as $slug) {
            $textWithoutPlaceholders = str_replace('{{' . $slug . '}}', '', $textWithoutPlaceholders);
        }

        $errorMessage = "Verse {$verse->number} in chapter {$chapterNumber} of book '{$bookName}'";
        $this->validateTextContent($textWithoutPlaceholders, 'invalid_verse_text_content', $errorMessage);
    }

    /**
     * Validate that reference text contains only valid text
     * Text should not contain USFM markers or other invalid characters
     */
    private function validateReferenceTextContent(VerseReferenceDTO $reference, string $bookName, int $chapterNumber, int $verseNumber): void
    {
        $text = $reference->text;
        $errorMessage = "Reference with slug '{$reference->slug}' in verse {$verseNumber} of chapter {$chapterNumber} in book '{$bookName}'";
        $this->validateTextContent($text, 'invalid_reference_text_content', $errorMessage);
    }

    /**
     * Validate that text contains only valid content
     * Checks for USFM markers and malformed placeholders
     */
    private function validateTextContent(string $text, string $errorCode, string $errorContext): void
    {
        // Check for USFM markers that should have been removed
        // Also checks for markers with + prefix (e.g., \+add, \+add*)
        if (preg_match('/\\\\\+?[a-z]+(?:\*)?\s*/i', $text)) {
            throw new VersionImportException(
                $errorCode,
                "{$errorContext} contains USFM markers that should have been removed"
            );
        }

        // Check for malformed or invalid placeholders (curly braces that aren't valid placeholders)
        if (preg_match('/\{[^{]*\}|\}[^{]*\{|\{\{|\}\}/', $text)) {
            throw new VersionImportException(
                $errorCode,
                "{$errorContext} contains invalid characters or malformed placeholders"
            );
        }
    }
}
