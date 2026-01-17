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
use Illuminate\Support\Facades\Log;

/**
 * Adapter for USFM (Unified Standard Format Markers) format
 * Each file represents one book of the Bible
 */
class UsfmAdapter implements VersionAdapterInterface
{
    /**
     * Paragraph markers that cause line breaks (should add \n to verse)
     */
    private const PARAGRAPH_BREAK_MARKERS = [
        'p', 'm', 'b', 'nb', 'pc', 'pr', 'pi', 'pi1', 'pi2', 'pi3', 'pi4',
        'mi', 'po', 'cls', 'pmo', 'pm', 'pmc', 'pmr',
        'q', 'q1', 'q2', 'q3', 'q4', 'qr', 'qc', 'qa', 'qm', 'qm1', 'qm2', 'qm3', 'qm4', 'qd',
        'li', 'li1', 'li2', 'li3', 'li4', 'lim', 'lim1', 'lim2', 'lim3', 'lim4', 'lh', 'lf',
    ];

    /**
     * Character formatting markers that should be removed (keeping only text)
     */
    private const FORMATTING_MARKERS = [
        'it', 'bd', 'em', 'bdit', 'sc', 'no', 'sup',
        'add', 'bk', 'dc', 'k', 'nd', 'ord', 'pn', 'png', 'qt', 'sig', 'sls', 'tl', 'wj',
        'w', 'wg', 'wh', 'wa', 'rb', 'pro', 'ndx', 'fig',
        'lik', 'liv', 'liv1', 'liv2', 'liv3', 'liv4', 'litl',
    ];

    /**
     * Note markers that should be processed as references
     */
    private const NOTE_MARKERS = [
        'f' => ['fr', 'ft'],   // Footnotes: \f + \fr REF \ft TEXT \f*
        'fe' => ['fr', 'ft'],  // Endnotes: \fe + \fr REF \ft TEXT \fe*
        'x' => ['xo', 'xt'],   // Cross references: \x + \xo REF \xt TEXT \x*
        'ef' => ['fr', 'ft'],  // Extended footnotes (study): \ef + \fr REF \ft TEXT \ef*
        'ex' => ['xo', 'xt'],  // Extended cross references (study): \ex + \xo REF \xt TEXT \ex*
    ];

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

        foreach ($lines as $lineNumber => $line) {
            $line = rtrim($line);

            // Skip empty lines
            if (empty($line)) continue;

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

            // Check for paragraph break markers at the start of line
            $paragraphMarker = $this->getParagraphMarker($line);
            if ($paragraphMarker !== null) {
                // If no verse exists, ignore paragraph marker
                if ($currentVerses->isEmpty()) continue;

                $lastVerse = $currentVerses->last();
                $currentVerses->pop();

                // Extract text after marker (remove marker and optional number)
                $markerLength = strlen($paragraphMarker);
                $paragraphText = trim(substr($line, $markerLength));

                if (!empty($paragraphText)) {
                    // If there's text after marker, it's a continuation of the verse
                    // Process references and formatting markers
                    // Start index after existing references to avoid duplicate slugs
                    $startIndex = $lastVerse->references->count() + 1;
                    [$newReferences, $cleanParagraphText] = $this->processReferences(
                        $paragraphText,
                        $startIndex,
                        $abbreviation->value,
                        $lineNumber + 1
                    );
                    $cleanParagraphText = $this->removeFormattingMarkers(
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

            // Extract verse from \v marker
            if (str_starts_with($line, '\\v ')) {
                preg_match('/^\\\v\s+(\d+)\s+(.+)$/', $line, $matches);
                
                if (!isset($matches[1]) || !isset($matches[2])) {
                    continue; // Skip invalid verse format
                }
                
                $verseNumber = (int) $matches[1];
                $verseContent = $matches[2] ?? '';

                // Extract references and clean verse text, replacing references with {{slug}}
                [$references, $cleanText] = $this->processReferences(
                    $verseContent,
                    1,
                    $abbreviation->value,
                    $lineNumber + 1
                );

                // Remove USFM formatting markers
                $cleanText = $this->removeFormattingMarkers(
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
            if (str_starts_with($line, '\\')) {
                // Extract marker name from line start
                preg_match('/^\\\\([a-z]+(?:\d+)?)/i', $line, $markerMatches);
                $marker = $markerMatches[1] ?? 'unknown';
                
                // Check if it's a known marker that we handle
                $knownMarkers = $this->getAllKnownMarkers();
                if (!in_array($marker, $knownMarkers, true) && $marker !== 'ch') {
                    $this->logUnmappedMarker($marker, $abbreviation->value, $lineNumber + 1);
                }
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
     * Supports all note types: footnotes (\f), endnotes (\fe), cross-references (\x),
     * extended footnotes (\ef), and extended cross-references (\ex)
     * Returns [references, cleanText]
     */
    private function processReferences(string $text, int $startIndex = 1, ?string $book = null, ?int $lineNumber = null): array
    {
        $references = new Collection();
        $index = $startIndex;

        // Process all note types defined in NOTE_MARKERS
        foreach (self::NOTE_MARKERS as $noteType => $contentMarkers) {
            $text = $this->processNoteMarkers($text, $noteType, $contentMarkers, $references, $index);
            $index = $startIndex + $references->count();
        }

        // Process inline quotation references: \rq TEXT \rq*
        // These are character markers that contain reference text directly
        $text = $this->processInlineQuotationReferences($text, $references, $index);
        $index = $startIndex + $references->count();

        // Remove any remaining note markers that weren't matched
        // Process each note type separately to match opening and closing markers correctly
        foreach (array_keys(self::NOTE_MARKERS) as $noteType) {
            $escapedNoteType = preg_quote($noteType, '/');
            // Remove complete note markers: \noteType ... \noteType*
            $text = preg_replace('/\\\\' . $escapedNoteType . '[^*]*\\\\' . $escapedNoteType . '\*/', '', $text);
            // Remove incomplete note markers: \noteType (without closing)
            $text = preg_replace('/\\\\' . $escapedNoteType . '[^\s]*/', '', $text);
        }

        // Detect unmapped markers in the text
        if ($book !== null && $lineNumber !== null) {
            $this->detectUnmappedMarkersInText($text, $book, $lineNumber);
        }

        // Clean up extra spaces
        $text = preg_replace('/\s+/', ' ', $text);

        return [$references, trim($text)];
    }

    /**
     * Process note markers (footnotes or cross-references)
     */
    private function processNoteMarkers(
        string $text,
        string $noteType,
        array $contentMarkers,
        Collection $references,
        int $startIndex
    ): string {
        $index = $startIndex;
        [$refMarker, $textMarker] = $contentMarkers;

        // Pattern to match note markers: \noteType + \refMarker REF \textMarker TEXT \noteType*
        // For cross-references, there may be multiple \xt markers, so we capture all content
        // Supports optional whitespace and captures text reliably
        $pattern = '/\\\\' . preg_quote($noteType, '/') . '\s*\+\s*\\\\' . preg_quote($refMarker, '/') . 
            '\s+([^\s\\\\]+)\s+\\\\' . preg_quote($textMarker, '/') . '\s*(.*?)\\\\' . 
            preg_quote($noteType, '/') . '\*/s';

        return preg_replace_callback($pattern, function ($match) use (&$references, &$index) {
            // Ensure match[2] exists and is not null
            if (!isset($match[2]) || $match[2] === null) {
                return ''; // Remove if no text captured
            }

            $referenceText = trim($match[2] ?? ''); // text of the reference

            // Skip if text is empty or null after trimming
            if (empty($referenceText)) {
                return ''; // Remove if no text
            }

            // Remove USFM formatting markers from reference text
            // This handles cases like nested markers, \xt (cross-reference), \ft (footnote text), etc.
            $referenceText = $this->removeFormattingMarkers($referenceText);

            $slug = (string) $index;

            // Create reference DTO
            $references->push(new VerseReferenceDTO(
                slug: $slug,
                text: $referenceText,
            ));

            $index++;

            // Return replacement with slug
            return '{{' . $slug . '}}';
        }, $text);
    }

    /**
     * Process inline quotation references: \rq TEXT \rq*
     * These are character markers that contain reference text directly (e.g., \rq Isaías 64:4 \rq*)
     */
    private function processInlineQuotationReferences(
        string $text,
        Collection $references,
        int $startIndex
    ): string {
        $index = $startIndex;

        // Pattern to match \rq TEXT \rq*
        $pattern = '/\\\\rq\s+(.*?)\\\\rq\*/s';

        return preg_replace_callback($pattern, function ($match) use (&$references, &$index) {
            // Ensure match[1] exists and is not null
            if (!isset($match[1]) || $match[1] === null) {
                return ''; // Remove if no text captured
            }

            $referenceText = trim($match[1] ?? ''); // text of the reference

            // Skip if text is empty or null after trimming
            if (empty($referenceText)) {
                return ''; // Remove if no text
            }

            // Remove USFM formatting markers from reference text
            $referenceText = $this->removeFormattingMarkers($referenceText);

            $slug = (string) $index;

            // Create reference DTO
            $references->push(new VerseReferenceDTO(
                slug: $slug,
                text: $referenceText,
            ));

            $index++;

            // Return replacement with slug
            return '{{' . $slug . '}}';
        }, $text);
    }

    /**
     * Get paragraph marker from line if it starts with one
     * Returns the marker name (e.g., '\p', '\q1', '\m') or null
     */
    private function getParagraphMarker(string $line): ?string
    {
        if (!str_starts_with($line, '\\')) return null;

        // Check each paragraph marker (sorted by length descending to match longer markers first)
        $sortedMarkers = self::PARAGRAPH_BREAK_MARKERS;
        usort($sortedMarkers, fn($a, $b) => strlen($b) <=> strlen($a));

        foreach ($sortedMarkers as $marker) {
            $markerPattern = '\\' . $marker;
            // Check if line starts with marker
            if (!str_starts_with($line, $markerPattern)) continue;

            $afterMarker = substr($line, strlen($markerPattern));

            // Marker should be followed by space, tab, newline, or end of line
            if (empty($afterMarker) || in_array($afterMarker[0], [' ', "\t", "\n", "\r"], true)) return $markerPattern;
        }

        return null;
    }

    /**
     * Remove USFM formatting markers from text
     * Removes character formatting markers (it, bd, em, etc.) and other character markers
     * Keeps the text content but removes the markup
     */
    private function removeFormattingMarkers(string $text, ?string $book = null, ?int $lineNumber = null): string
    {
        // Build pattern for all formatting markers
        $markersPattern = implode('|', array_map('preg_quote', self::FORMATTING_MARKERS));
        
        // Remove closing markers (e.g., \it*, \bd*)
        $text = preg_replace('/\\\\(' . $markersPattern . ')\*/', '', $text);
        
        // Remove opening markers with optional attributes (e.g., \it , \w|lemma="grace" )
        // Pattern matches: \marker, \marker|attributes, \marker followed by space or end
        $text = preg_replace('/\\\\(' . $markersPattern . ')(?:\|[^\\\\]*)?(?:\s+|$)/', '', $text);
        
        // Detect unmapped markers before removing them generically
        if ($book !== null && $lineNumber !== null) {
            $this->detectUnmappedMarkersInText($text, $book, $lineNumber);
        }
        
        // Remove any remaining character markers that follow the pattern \marker or \marker*
        // This catches any markers we might have missed (generic pattern)
        $text = preg_replace('/\\\\[a-z]+\*/', '', $text); // Remove closing markers
        $text = preg_replace('/\\\\[a-z]+(?:\|[^\\\\]*)?(?:\s+|$)/', '', $text); // Remove opening markers with optional attributes

        // Clean up extra spaces that might have been left
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Get all known markers (paragraph, formatting, and note markers)
     */
    private function getAllKnownMarkers(): array
    {
        $knownMarkers = array_merge(
            self::PARAGRAPH_BREAK_MARKERS,
            self::FORMATTING_MARKERS,
            array_keys(self::NOTE_MARKERS),
            ['h', 'c', 'v', 'rq'] // Additional known markers
        );

        // Add note content markers (fr, ft, xo, xt)
        $noteContentMarkers = [];
        foreach (self::NOTE_MARKERS as $contentMarkers) {
            $noteContentMarkers = array_merge($noteContentMarkers, $contentMarkers);
        }
        $knownMarkers = array_merge($knownMarkers, $noteContentMarkers);

        return array_unique($knownMarkers);
    }

    /**
     * Detect and log unmapped markers in text
     */
    private function detectUnmappedMarkersInText(string $text, string $book, int $lineNumber): void
    {
        $knownMarkers = $this->getAllKnownMarkers();
        
        // Pattern to match all markers: \marker, \marker*, \marker|attr, \marker|attr*
        // Matches: \ followed by letters/numbers, optional |attributes, optional *
        // This captures both opening and closing markers
        // Group 1: marker name, Group 2: optional attributes, Group 3: optional *
        preg_match_all('/\\\\([a-z]+(?:\d+)?)(?:\|([^\\\\\s*]+))?(\*)?/i', $text, $matches, PREG_SET_ORDER);
        
        if (!empty($matches)) {
            foreach ($matches as $match) {
                // Extract marker name (group 1)
                $markerName = strtolower($match[1]);
                
                // Check if marker is known
                if (!in_array($markerName, $knownMarkers, true)) {
                    $this->logUnmappedMarker($markerName, $book, $lineNumber);
                }
            }
        }
    }

    /**
     * Log unmapped marker
     */
    private function logUnmappedMarker(string $marker, string $book, int $lineNumber): void
    {
        Log::warning('Unmapped USFM marker detected', [
            'marker' => $marker,
            'book' => $book,
            'line' => $lineNumber,
        ]);
    }
}
