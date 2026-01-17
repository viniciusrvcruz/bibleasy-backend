<?php

namespace App\Services\Version\Adapters\Usfm;

use Illuminate\Support\Facades\Log;

/**
 * Removes USFM formatting markers from text
 */
class UsfmMarkerCleaner
{
    /**
     * Remove USFM formatting markers from text
     * Removes character formatting markers (it, bd, em, etc.) and other character markers
     * Keeps the text content but removes the markup
     */
    public function clean(string $text, ?string $book = null, ?int $lineNumber = null): string
    {
        // Build pattern for all formatting markers
        $markersPattern = implode('|', array_map('preg_quote', UsfmMarkers::FORMATTING_MARKERS));

        // Remove closing markers (e.g., \it*, \bd*)
        $text = preg_replace('/\\\\(' . $markersPattern . ')\*/', '', $text);

        // Remove opening markers with optional attributes (e.g., \it , \w|lemma="grace" )
        // Pattern matches: \marker, \marker|attributes, \marker followed by space or end
        $text = preg_replace('/\\\\(' . $markersPattern . ')(?:\|[^\\\\]*)?(?:\s+|$)/', '', $text);

        // Detect unmapped markers before removing them generically
        if ($book !== null && $lineNumber !== null) {
            $this->detectUnmappedMarkers($text, $book, $lineNumber);
        }

        // Remove any remaining character markers that follow the pattern \marker or \marker*
        // This catches any markers we might have missed (generic pattern)
        // Also handles markers that start with + (e.g., \+add, \+add*)
        $text = preg_replace('/\\\\\+?[a-z]+\*/', '', $text); // Remove closing markers (including \+marker*)
        $text = preg_replace('/\\\\\+?[a-z]+(?:\|[^\\\\]*)?(?:\s+|$)/', '', $text); // Remove opening markers with optional attributes (including \+marker)

        // Clean up extra spaces that might have been left
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Detect and log unmapped markers in text
     */
    public function detectUnmappedMarkers(string $text, string $book, int $lineNumber): void
    {
        $knownMarkers = UsfmMarkers::getAllKnownMarkers();

        // Pattern to match all markers: \marker, \marker*, \marker|attr, \marker|attr*
        // Also matches markers with + prefix: \+marker, \+marker*
        // Matches: \ followed by optional +, then letters/numbers, optional |attributes, optional *
        // This captures both opening and closing markers
        // Group 1: marker name (without +), Group 2: optional attributes, Group 3: optional *
        preg_match_all('/\\\\\+?([a-z]+(?:\d+)?)(?:\|([^\\\\\s*]+))?(\*)?/i', $text, $matches, PREG_SET_ORDER);

        if (empty($matches)) return;

        foreach ($matches as $match) {
            // Extract marker name (group 1)
            $markerName = strtolower($match[1]);
            
            // Check if marker is known
            if (!in_array($markerName, $knownMarkers, true)) {
                $this->logUnmappedMarker($markerName, $book, $lineNumber);
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
