<?php

namespace App\Services\Version\Adapters;

use App\Enums\BookAbbreviationEnum;
use App\Services\Version\DTOs\VersionDTO;
use App\Services\Version\DTOs\BookDTO;
use App\Services\Version\DTOs\ChapterDTO;
use App\Services\Version\DTOs\VerseDTO;
use App\Services\Version\DTOs\VerseReferenceDTO;
use App\Services\Version\Interfaces\VersionAdapterInterface;
use App\Exceptions\Version\VersionImportException;
use Illuminate\Support\Collection;

/**
 * Adapter for USFM (Unified Standard Format Markers) format
 * Each file represents one book of the Bible
 */
class UsfmAdapter implements VersionAdapterInterface
{
    public function adapt(array $files): VersionDTO
    {
        $books = collect($files)->map(function ($file) {
            $this->validateFile($file);

            $bookAbbreviation = $this->getBookAbbreviationFromFileName($file->fileName);
            $content = $file->content;

            return $this->parseBook($content, $bookAbbreviation);
        });

        return new VersionDTO($books);
    }

    /**
     * Validate file extension and name
     */
    private function validateFile($file): void
    {
        if (strtolower($file->extension) !== 'usfm') {
            throw new VersionImportException(
                'invalid_file_extension',
                'File must have .usfm extension'
            );
        }
    }

    /**
     * Extract book abbreviation from file name
     * File name should be like "mat.usfm" or "MAT.usfm"
     */
    private function getBookAbbreviationFromFileName(string $fileName): BookAbbreviationEnum
    {
        $nameWithoutExtension = pathinfo($fileName, PATHINFO_FILENAME);
        $normalized = strtolower($nameWithoutExtension);

        return BookAbbreviationEnum::tryFrom($normalized) ?? throw new VersionImportException(
            'invalid_file_name',
            "File name '{$fileName}' does not match any book abbreviation from BookAbbreviationEnum"
        );
    }

    /**
     * Parse USFM content into BookDTO
     */
    private function parseBook(string $content, BookAbbreviationEnum $abbreviation): BookDTO
    {
        $lines = explode("\n", $content);
        $bookName = null;
        $chapters = new Collection();
        $currentVerses = new Collection();
        $currentChapterNumber = null;

        foreach ($lines as $line) {
            $line = rtrim($line);

            // Check for \h marker at the start of line
            if (str_starts_with($line, '\\h ')) {
                $bookName = trim(substr($line, 3));
                continue;
            }

            // Check for \c marker (but not \ch) at the start of line
            if (str_starts_with($line, '\\c ')) {
                // Save previous chapter if exists
                if ($currentChapterNumber !== null && $currentVerses->isNotEmpty()) {
                    $chapters->push(new ChapterDTO($currentChapterNumber, $currentVerses));
                }

                // Extract chapter number
                preg_match('/^\\\c\s+(\d+)/', $line, $matches);
                $currentChapterNumber = (int) $matches[1];
                $currentVerses = new Collection();
                continue;
            }

            // Check for \p marker at the start of line
            // \p can be empty (paragraph break) or contain text (verse continuation)
            if (str_starts_with($line, '\\p')) {
                // If no verse exists, ignore \p marker
                if ($currentVerses->isEmpty()) continue;

                $lastVerse = $currentVerses->last();
                $currentVerses->pop();

                // Extract text after \p marker
                $paragraphText = trim(substr($line, 2)); // Remove \p from the beginning

                if (!empty($paragraphText)) {
                    // If there's text after \p, it's a continuation of the verse
                    // Process references and formatting markers
                    [$newReferences, $cleanParagraphText] = $this->processReferences($paragraphText);
                    $cleanParagraphText = $this->removeFormattingMarkers($cleanParagraphText);

                    // Merge references with existing ones
                    $allReferences = $lastVerse->references->merge($newReferences);

                    // Add paragraph text to verse (with newline before the text)
                    $updatedText = $lastVerse->text . "\n" . $cleanParagraphText;
                } else {
                    // If \p is empty, it's just a paragraph break
                    $allReferences = $lastVerse->references;
                    $updatedText = $lastVerse->text . "\n";
                }

                $currentVerses->push(new VerseDTO(
                    $lastVerse->number,
                    $updatedText,
                    $allReferences
                ));
                continue;
            }

            // Extract verse from \v marker
            if (str_starts_with($line, '\\v ')) {
                preg_match('/^\\\v\s+(\d+)\s+(.+)$/', $line, $matches);
                
                if (!isset($matches[1]) || !isset($matches[2])) {
                    continue; // Skip invalid verse format
                }
                
                $verseNumber = (int) $matches[1];
                $verseContent = $matches[2] ?? '';

                // Extract references and clean verse text, replacing references with {{slug}}
                [$references, $cleanText] = $this->processReferences($verseContent);

                // Remove USFM formatting markers
                $cleanText = $this->removeFormattingMarkers($cleanText);

                $currentVerses->push(new VerseDTO(
                    $verseNumber,
                    $cleanText,
                    $references
                ));
                continue;
            }
        }

        // Save last chapter
        if ($currentChapterNumber !== null && $currentVerses->isNotEmpty()) {
            $chapters->push(new ChapterDTO($currentChapterNumber, $currentVerses));
        }

        if (!$bookName) {
            throw new VersionImportException(
                'missing_book_name',
                'Book name (\h marker) not found in USFM file'
            );
        }

        return new BookDTO($bookName, $abbreviation, $chapters);
    }

    /**
     * Process references from verse text: extract references and replace with {{slug}} in text
     * Format: \f + \fr 1:1 \ft reference text \f*
     * Returns [references, cleanText]
     */
    private function processReferences(string $text): array
    {
        $references = new Collection();
        $index = 1;

        // Pattern to match \f + \fr reference \ft text \f*
        // The pattern uses non-greedy matching to capture text until \f*
        // Updated to better capture reference text that may contain special characters
        // Pattern allows for optional whitespace and captures text more reliably
        // Made \ft whitespace optional to handle cases where there's no space after \ft
        $pattern = '/\\\f\s*\+\s*\\\fr\s+([^\s\\\]+)\s+\\\ft\s*(.*?)\\\f\*/s';

        // Replace each reference with {{slug}} and extract reference data
        $cleanText = preg_replace_callback($pattern, function ($match) use (&$references, &$index) {
            // Ensure match[2] exists and is not null
            if (!isset($match[2]) || $match[2] === null) {
                return ''; // Remove if no text captured
            }

            $referenceText = trim($match[2] ?? ''); // text of the reference

            // Skip if text is empty or null after trimming
            if (empty($referenceText) || $referenceText === '') {
                return ''; // Remove if no text
            }

            $slug = (string) $index;

            // Create reference DTO - ensure text is never null
            $references->push(new VerseReferenceDTO(
                slug: $slug,
                text: $referenceText,
            ));

            $index++;

            // Return replacement with slug
            return '{{' . $slug . '}}';
        }, $text);

        // Remove any remaining \f markers that weren't matched
        $cleanText = preg_replace('/\\\f[^*]*\\\f\*/', '', $cleanText);
        $cleanText = preg_replace('/\\\f[^\s]*/', '', $cleanText);

        // Clean up extra spaces
        $cleanText = preg_replace('/\s+/', ' ', $cleanText);

        return [$references, trim($cleanText)];
    }

    /**
     * Remove USFM formatting markers from text
     * Removes markers like \it, \it*, \bd, \bd*, \em, \em*, etc.
     */
    private function removeFormattingMarkers(string $text): string
    {
        // Remove all USFM markers that follow the pattern \marker or \marker*
        $text = preg_replace('/\\\\[a-z]+\*/', '', $text); // Remove closing markers (e.g., \it*)
        $text = preg_replace('/\\\\[a-z]+(?:\s+|$)/', '', $text); // Remove opening markers (e.g., \it)

        // Clean up extra spaces that might have been left
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
