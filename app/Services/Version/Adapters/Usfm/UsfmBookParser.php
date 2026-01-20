<?php

namespace App\Services\Version\Adapters\Usfm;

use App\Enums\BookAbbreviationEnum;
use App\Services\Version\DTOs\BookDTO;
use App\Services\Version\DTOs\ChapterDTO;
use App\Services\Version\DTOs\VerseDTO;
use App\Exceptions\Version\VersionImportException;
use Illuminate\Support\Collection;

/**
 * Orchestrates the parsing of a complete USFM book
 */
class UsfmBookParser
{
    public function __construct(
        private readonly UsfmLineParser $lineParser,
        private readonly UsfmReferenceProcessor $referenceProcessor,
        private readonly UsfmMarkerCleaner $markerCleaner
    ) {}

    /**
     * Parse USFM content into BookDTO
     */
    public function parse(string $content, BookAbbreviationEnum $abbreviation): BookDTO
    {
        $lines = explode("\n", $content);
        $bookName = null;
        $chapters = new Collection();
        $currentVerses = new Collection();
        $currentChapterNumber = null;

        foreach ($lines as $lineNumber => $line) {
            $line = rtrim($line);

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Process book name
            $parsedBookName = $this->lineParser->parseBookName($line);
            if ($parsedBookName !== null) {
                $bookName = $parsedBookName;
                continue;
            }

            // Process chapter
            $parsedChapterNumber = $this->lineParser->parseChapterNumber($line);
            if ($parsedChapterNumber !== null) {
                // Save previous chapter if exists
                if ($currentChapterNumber !== null && $currentVerses->isNotEmpty()) {
                    $chapters->push(new ChapterDTO($currentChapterNumber, $currentVerses));
                }

                $currentChapterNumber = $parsedChapterNumber;
                $currentVerses = new Collection();
                continue;
            }

            // Process paragraph markers
            if ($this->lineParser->isParagraphMarker($line)) {
                // If no verse exists, ignore paragraph marker
                if ($currentVerses->isEmpty()) {
                    continue;
                }

                $lastVerse = $currentVerses->last();
                $currentVerses->pop();

                // Extract text after marker
                $paragraphText = $this->lineParser->getParagraphText($line);

                if ($paragraphText !== null) {
                    // If there's text after marker, it's a continuation of the verse
                    // Process references and formatting markers
                    // Start index after existing references to avoid duplicate slugs
                    $startIndex = $lastVerse->references->count() + 1;
                    [$newReferences, $cleanParagraphText] = $this->referenceProcessor->process(
                        $paragraphText,
                        $startIndex,
                        $abbreviation->value,
                        $lineNumber + 1
                    );
                    $cleanParagraphText = $this->markerCleaner->clean(
                        $cleanParagraphText,
                        $abbreviation->value,
                        $lineNumber + 1
                    );

                    // Merge references with existing ones
                    $allReferences = $lastVerse->references->merge($newReferences);

                    // Add paragraph text to verse (with newline before the text)
                    $updatedText = $lastVerse->text . "\n" . $cleanParagraphText;
                } else {
                    // If marker is empty, it's just a paragraph break
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

            // Process verse
            $parsedVerse = $this->lineParser->parseVerse($line, $abbreviation->value);
            if ($parsedVerse !== null) {
                $verseNumber = $parsedVerse['number'];
                $verseContent = $parsedVerse['content'];

                // Extract references and clean verse text, replacing references with {{slug}}
                [$references, $cleanText] = $this->referenceProcessor->process(
                    $verseContent,
                    1,
                    $abbreviation->value,
                    $lineNumber + 1
                );

                // Remove USFM formatting markers
                $cleanText = $this->markerCleaner->clean(
                    $cleanText,
                    $abbreviation->value,
                    $lineNumber + 1
                );

                $currentVerses->push(new VerseDTO(
                    $verseNumber,
                    $cleanText,
                    $references
                ));
                continue;
            }

            // Check if line starts with a marker but wasn't processed
            $markerName = $this->lineParser->extractMarkerName($line);
            if ($markerName === null) continue;

            // Check if it's a known marker that we handle
            $knownMarkers = UsfmMarkers::getAllKnownMarkers();
            if (!in_array($markerName, $knownMarkers, true) && $markerName !== 'ch') {
                $this->logUnmappedMarker($markerName, $abbreviation->value, $lineNumber + 1);
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
     * Log unmapped marker
     */
    private function logUnmappedMarker(string $marker, string $book, int $lineNumber): void
    {
        $this->markerCleaner->detectUnmappedMarkers('\\' . $marker, $book, $lineNumber);
    }
}
