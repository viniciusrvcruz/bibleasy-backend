<?php

namespace App\Services\Version\Adapters\Usfm;

use App\Services\Version\DTOs\VerseReferenceDTO;
use Illuminate\Support\Collection;

/**
 * Processes and extracts references from USFM text
 */
class UsfmReferenceProcessor
{
    public function __construct(
        private readonly UsfmMarkerCleaner $markerCleaner
    ) {}

    /**
     * Process references from verse text: extract references and replace with {{slug}} in text
     * Supports all note types: footnotes (\f), endnotes (\fe), cross-references (\x),
     * extended footnotes (\ef), and extended cross-references (\ex)
     * Returns [references, cleanText]
     */
    public function process(string $text, int $startIndex = 1, ?string $book = null, ?int $lineNumber = null): array
    {
        $references = new Collection();
        $currentIndex = $startIndex;

        // Process all note types defined in NOTE_MARKERS
        foreach (UsfmMarkers::NOTE_MARKERS as $noteType => $contentMarkers) {
            $text = $this->processNoteMarkers($text, $noteType, $contentMarkers, $references, $currentIndex);
            $currentIndex = $startIndex + $references->count();
        }

        // Process inline quotation references: \rq TEXT \rq*
        $text = $this->processInlineQuotationReferences($text, $references, $currentIndex);

        // Remove any remaining unmatched note markers
        $text = $this->removeUnmatchedNoteMarkers($text);

        // Detect unmapped markers in the text
        if ($book !== null && $lineNumber !== null) {
            $this->markerCleaner->detectUnmappedMarkers($text, $book, $lineNumber);
        }

        // Clean up extra spaces
        $text = preg_replace('/\s+/', ' ', $text);

        return [$references, trim($text)];
    }

    /**
     * Process note markers (footnotes or cross-references)
     * 
     * @param Collection<int, VerseReferenceDTO> $references
     */
    private function processNoteMarkers(
        string $text,
        string $noteType,
        array $contentMarkers,
        Collection $references,
        int $startIndex
    ): string {
        [$refMarker, $textMarker] = $contentMarkers;
        $index = $startIndex;

        // Pattern to match note markers: \noteType + \refMarker REF \textMarker TEXT \noteType*
        $pattern = $this->buildNoteMarkerPattern($noteType, $refMarker, $textMarker);

        return preg_replace_callback($pattern, function ($match) use (&$references, &$index) {
            $referenceText = $this->extractAndCleanReferenceText($match[2] ?? '');

            if (empty($referenceText)) return '';

            return $this->createReferenceAndGetSlug($references, $index++, $referenceText);
        }, $text);
    }

    /**
     * Process inline quotation references: \rq TEXT \rq*
     * 
     * @param Collection<int, VerseReferenceDTO> $references
     */
    private function processInlineQuotationReferences(
        string $text,
        Collection $references,
        int $startIndex
    ): string {
        $index = $startIndex;
        $pattern = '/\\\\rq\s+(.*?)\\\\rq\*/s';

        return preg_replace_callback($pattern, function ($match) use (&$references, &$index) {
            $referenceText = $this->extractAndCleanReferenceText($match[1] ?? '');
            
            if (empty($referenceText)) return '';

            return $this->createReferenceAndGetSlug($references, $index++, $referenceText);
        }, $text);
    }

    /**
     * Remove any remaining note markers that weren't matched
     */
    private function removeUnmatchedNoteMarkers(string $text): string
    {
        foreach (array_keys(UsfmMarkers::NOTE_MARKERS) as $noteType) {
            $escapedNoteType = preg_quote($noteType, '/');
            
            // Remove complete note markers: \noteType ... \noteType*
            $text = preg_replace('/\\\\' . $escapedNoteType . '[^*]*\\\\' . $escapedNoteType . '\*/', '', $text);
            
            // Remove incomplete note markers: \noteType (without closing)
            $text = preg_replace('/\\\\' . $escapedNoteType . '[^\s]*/', '', $text);
        }

        return $text;
    }

    /**
     * Build regex pattern for note markers
     */
    private function buildNoteMarkerPattern(string $noteType, string $refMarker, string $textMarker): string
    {
        $escapedNoteType = preg_quote($noteType, '/');
        $escapedRefMarker = preg_quote($refMarker, '/');
        $escapedTextMarker = preg_quote($textMarker, '/');

        return '/\\\\' . $escapedNoteType . '\s*\+\s*\\\\' . $escapedRefMarker . 
            '\s+([^\s\\\\]+)\s+\\\\' . $escapedTextMarker . '\s*(.*?)\\\\' . 
            $escapedNoteType . '\*/s';
    }

    /**
     * Extract and clean reference text from match
     */
    private function extractAndCleanReferenceText(?string $rawText): string
    {
        if ($rawText === null) return '';

        $referenceText = trim($rawText);

        if (empty($referenceText)) return '';

        // Remove USFM formatting markers from reference text
        return $this->markerCleaner->clean($referenceText);
    }

    /**
     * Create reference DTO and return slug placeholder
     * 
     * @param Collection<int, VerseReferenceDTO> $references
     */
    private function createReferenceAndGetSlug(Collection $references, int $index, string $referenceText): string
    {
        $slug = (string) $index;

        $references->push(new VerseReferenceDTO(
            slug: $slug,
            text: $referenceText,
        ));

        return '{{' . $slug . '}}';
    }
}
