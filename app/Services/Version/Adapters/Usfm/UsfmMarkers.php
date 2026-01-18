<?php

namespace App\Services\Version\Adapters\Usfm;

/**
 * Centralizes all USFM marker definitions
 */
class UsfmMarkers
{
    /**
     * Paragraph markers that cause line breaks (should add \n to verse)
     */
    public const PARAGRAPH_BREAK_MARKERS = [
        'p', 'm', 'b', 'nb', 'pc', 'pr', 'pi', 'pi1', 'pi2', 'pi3', 'pi4',
        'mi', 'po', 'cls', 'pmo', 'pm', 'pmc', 'pmr',
        'q', 'q1', 'q2', 'q3', 'q4', 'qr', 'qc', 'qa', 'qm', 'qm1', 'qm2', 'qm3', 'qm4', 'qd',
        'li', 'li1', 'li2', 'li3', 'li4', 'lim', 'lim1', 'lim2', 'lim3', 'lim4', 'lh', 'lf',
    ];

    /**
     * Character formatting markers that should be removed (keeping only text)
     */
    public const FORMATTING_MARKERS = [
        'it', 'bd', 'em', 'bdit', 'sc', 'no', 'sup',
        'add', 'bk', 'dc', 'k', 'nd', 'ord', 'pn', 'png', 'qt', 'sig', 'sls', 'tl', 'wj',
        'w', 'wg', 'wh', 'wa', 'rb', 'pro', 'ndx', 'fig',
        'lik', 'liv', 'liv1', 'liv2', 'liv3', 'liv4', 'litl',
    ];

    /**
     * Note markers that should be processed as references
     */
    public const NOTE_MARKERS = [
        'f' => ['fr', 'ft'],   // Footnotes: \f + \fr REF \ft TEXT \f*
        'fe' => ['fr', 'ft'],  // Endnotes: \fe + \fr REF \ft TEXT \fe*
        'x' => ['xo', 'xt'],   // Cross references: \x + \xo REF \xt TEXT \x*
        'ef' => ['fr', 'ft'],  // Extended footnotes (study): \ef + \fr REF \ft TEXT \ef*
        'ex' => ['xo', 'xt'],  // Extended cross references (study): \ex + \xo REF \xt TEXT \ex*
    ];

    /**
     * Markers to ignore when logging unmapped markers
     */
    public const LOG_IGNORED_MARKERS = [
        'id', 'ide', 'toc1', 'toc2', 'toc3', 'mt1', 'd', 's1', 'sp',
    ];

    /**
     * Get all known markers (paragraph, formatting, and note markers)
     */
    public static function getAllKnownMarkers(): array
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
}
