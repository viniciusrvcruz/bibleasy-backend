<?php

namespace App\Services\Version\Adapters\Usfm;

use Illuminate\Support\Facades\Log;

/**
 * Parses individual USFM lines to extract structured information
 */
class UsfmLineParser
{
    /**
     * Extract book name from \h marker
     */
    public function parseBookName(string $line): ?string
    {
        if (!str_starts_with($line, '\\h ')) return null;

        return trim(substr($line, 3));
    }

    /**
     * Extract book abbreviation from \id marker
     */
    public function parseBookAbbreviation(string $line): ?string
    {
        if (!str_starts_with($line, '\\id ')) return null;

        // Extract text after \id marker
        $content = trim(substr($line, 4));

        return substr($content, 0, 3);
    }

    /**
     * Extract chapter number from \c marker
     */
    public function parseChapterNumber(string $line): ?int
    {
        if (!str_starts_with($line, '\\c ')) return null;

        preg_match('/^\\\c\s+(\d+)/', $line, $matches);

        return isset($matches[1]) ? (int) $matches[1] : null;
    }

    /**
     * Extract verse number and content from \v marker
     * Returns ['number' => int, 'content' => string] or null
     */
    public function parseVerse(string $line, string $book): ?array
    {
        if (!str_starts_with($line, '\\v ')) return null;

        preg_match('/^\\\v\s+(\d+)\s+(.+)$/', $line, $matches);

        if (!isset($matches[1]) || !isset($matches[2])) {
            Log::warning('Verse not parsed', [
                'book' => $book,
                'line' => $line,
            ]);

            return null;
        };

        return [
            'number' => (int) $matches[1],
            'content' => $matches[2],
        ];
    }

    /**
     * Check if line starts with a paragraph marker
     */
    public function isParagraphMarker(string $line): bool
    {
        return $this->getParagraphMarker($line) !== null;
    }

    /**
     * Get paragraph marker from line if it starts with one
     * Returns the marker name (e.g., '\p', '\q1', '\m') or null
     */
    public function getParagraphMarker(string $line): ?string
    {
        if (!str_starts_with($line, '\\')) return null;

        // Check each paragraph marker (sorted by length descending to match longer markers first)
        $sortedMarkers = UsfmMarkers::PARAGRAPH_BREAK_MARKERS;
        usort($sortedMarkers, fn($a, $b) => strlen($b) <=> strlen($a));

        foreach ($sortedMarkers as $marker) {
            $markerPattern = '\\' . $marker;
            // Check if line starts with marker
            if (!str_starts_with($line, $markerPattern)) {
                continue;
            }

            $afterMarker = substr($line, strlen($markerPattern));

            // Marker should be followed by space, tab, newline, or end of line
            if (empty($afterMarker) || in_array($afterMarker[0], [' ', "\t", "\n", "\r"], true)) {
                return $markerPattern;
            }
        }

        return null;
    }

    /**
     * Extract text after paragraph marker
     */
    public function getParagraphText(string $line): ?string
    {
        $paragraphMarker = $this->getParagraphMarker($line);

        if ($paragraphMarker === null) return null;

        $markerLength = strlen($paragraphMarker);
        $paragraphText = trim(substr($line, $markerLength));

        return $paragraphText ?: null;
    }

    /**
     * Extract marker name from line start
     */
    public function extractMarkerName(string $line): ?string
    {
        if (!str_starts_with($line, '\\')) return null;

        preg_match('/^\\\\([a-z]+(?:\d+)?)/i', $line, $markerMatches);

        return $markerMatches[1] ?? null;
    }
}
