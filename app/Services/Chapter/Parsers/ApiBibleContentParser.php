<?php

namespace App\Services\Chapter\Parsers;

use App\Enums\VerseTitleTypeEnum;
use App\Services\Chapter\DTOs\VerseReferenceResponseDTO;
use App\Services\Chapter\DTOs\VerseResponseDTO;
use App\Services\Chapter\DTOs\VerseTitleDTO;
use App\Services\Version\Adapters\Usfm\UsfmMarkers;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Parses api.bible chapter content array into verse DTOs.
 * Handles section/reference titles, verse text, notes (footnotes/cross-refs), and paragraph breaks.
 * Warnings (unknown styles, skipped content) are collected during parse and logged once at the end, only if log is bound.
 */
class ApiBibleContentParser
{
    private const CHAPTER_LABEL_STYLE = 'cl';

    private const SECTION_TITLE_STYLES = ['d', 's', 's1', 's2', 's3', 'qa'];

    private const REFERENCE_TITLE_STYLES = ['r', 'mr'];

    private const NOTE_STYLES = ['f', 'fe', 'x', 'ef', 'ex'];

    private const NOTE_CONTENT_STYLES = ['ft', 'fqa', 'xt'];

    /** @var array<int, array{titles: array<VerseTitleDTO>, references: array<VerseReferenceResponseDTO>, text: string, ref_prefix: string}> */
    private array $versesData = [];

    /** @var array<VerseTitleDTO> */
    private array $titleBuffer = [];

    /** @var array<int, int> */
    private array $verseSlugCount = [];

    private ?int $currentVerseNumber = null;

    private ?string $lastParaStyle = null;

    private ?int $lastVerseInPara = null;

    /** @var array<int, bool> */
    private array $verseReceivedContentThisPara = [];

    private string $parseContext = '';

    /** @var array<int, array{message: string, context: array<string, mixed>}> */
    private array $warnings = [];

    /**
     * @return Collection<int, VerseResponseDTO>
     */
    public function parse(array $content, string $bookId, string $chapterNumber): Collection
    {
        $this->reset();
        $this->parseContext = $bookId . '.' . $chapterNumber;
        $knownStyles = $this->getKnownParagraphStyles();

        foreach ($content as $para) {
            if (! $this->isParagraphTag($para)) {
                continue;
            }

            $style = $para['attrs']['style'] ?? '';
            $items = $para['items'] ?? [];
            $this->lastParaStyle = $style;

            if ($style === self::CHAPTER_LABEL_STYLE) {
                $this->processChapterLabelParagraph($items, $bookId, $chapterNumber, $style);
                continue;
            }

            if ($this->isSectionTitleStyle($style)) {
                $this->appendSectionTitleFromItems($items);
                continue;
            }

            if ($this->isReferenceTitleStyle($style)) {
                $this->appendReferenceTitleFromItems($items);
                continue;
            }

            if ($style !== '' && ! in_array($style, $knownStyles, true)) {
                $this->warnUnknownParagraphStyle($style);
            }

            $isParagraphBreak = in_array($style, UsfmMarkers::PARAGRAPH_BREAK_MARKERS, true);
            $this->processVerseParagraph($items, $bookId, $chapterNumber, $isParagraphBreak, $style);
        }

        $this->flushWarnings();

        return $this->buildVerseDTOs();
    }

    private function isParagraphTag(array $para): bool
    {
        return ($para['name'] ?? '') === 'para' && ($para['type'] ?? '') === 'tag';
    }

    private function isSectionTitleStyle(string $style): bool
    {
        return in_array($style, self::SECTION_TITLE_STYLES, true);
    }

    private function isReferenceTitleStyle(string $style): bool
    {
        return in_array($style, self::REFERENCE_TITLE_STYLES, true);
    }

    private function appendSectionTitleFromItems(array $items): void
    {
        $text = $this->extractTextFromItems($items);
        if ($text !== '') {
            $this->titleBuffer[] = new VerseTitleDTO($text, VerseTitleTypeEnum::SECTION);
        }
    }

    private function appendReferenceTitleFromItems(array $items): void
    {
        $text = $this->extractTextFromItems($items);
        if ($text !== '') {
            $this->titleBuffer[] = new VerseTitleDTO($text, VerseTitleTypeEnum::REFERENCE);
        }
    }

    private function warnUnknownParagraphStyle(string $style): void
    {
        $this->addWarning('ApiBibleContentParser: unknown paragraph style, content may be lost.', [
            'context' => $this->parseContext,
            'style' => $style,
        ]);
    }

    private function addWarning(string $message, array $context = []): void
    {
        $this->warnings[] = ['message' => $message, 'context' => $context];
    }

    private function flushWarnings(): void
    {
        if (! function_exists('app') || ! app()->bound('log')) {
            return;
        }
        foreach ($this->warnings as $w) {
            Log::warning($w['message'], $w['context']);
        }
        $this->warnings = [];
    }

    /**
     * @return array<int, string>
     */
    private function getKnownParagraphStyles(): array
    {
        return array_values(array_unique(array_merge(
            [self::CHAPTER_LABEL_STYLE],
            self::SECTION_TITLE_STYLES,
            self::REFERENCE_TITLE_STYLES,
            UsfmMarkers::PARAGRAPH_BREAK_MARKERS
        )));
    }

    private function reset(): void
    {
        $this->versesData = [];
        $this->titleBuffer = [];
        $this->verseSlugCount = [];
        $this->currentVerseNumber = null;
        $this->lastParaStyle = null;
        $this->lastVerseInPara = null;
        $this->verseReceivedContentThisPara = [];
        $this->parseContext = '';
        $this->warnings = [];
    }

    /**
     * Chapter label paragraph: ignore as title, only process notes (assign to verse by verseId).
     *
     * @param array<int, array<string, mixed>> $items
     */
    private function processChapterLabelParagraph(array $items, string $bookId, string $chapterNumber, string $paragraphStyle): void
    {
        $this->processItems($items, $bookId, $chapterNumber, false, true, $paragraphStyle);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function processVerseParagraph(array $items, string $bookId, string $chapterNumber, bool $isParagraphBreak, string $paragraphStyle): void
    {
        $this->verseReceivedContentThisPara = [];
        $this->processItems($items, $bookId, $chapterNumber, $isParagraphBreak, false, $paragraphStyle);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function processItems(
        array $items,
        string $bookId,
        string $chapterNumber,
        bool $isParagraphBreak,
        bool $isChapterLabelContext,
        string $paragraphStyle = ''
    ): void {
        foreach ($items as $item) {
            $itemType = $item['type'] ?? '';
            $itemName = $item['name'] ?? '';

            if ($this->isVerseTag($itemName, $itemType)) {
                $this->handleVerseTag($item);
                continue;
            }

            if ($itemType === 'text') {
                $this->handleTextItem($item, $bookId, $chapterNumber, $isParagraphBreak, $isChapterLabelContext, $paragraphStyle);
                continue;
            }

            if ($this->isNoteTag($itemName, $itemType)) {
                $this->handleNoteTag($item, $bookId, $chapterNumber, $isChapterLabelContext);
                continue;
            }

            if ($this->isCharacterTag($itemName, $itemType)) {
                $this->dispatchCharacterTag($item, $isParagraphBreak, $isChapterLabelContext, $paragraphStyle);
                continue;
            }

            $this->warnUnhandledItemIfNeeded($item, $paragraphStyle);
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function dispatchCharacterTag(array $item, bool $isParagraphBreak, bool $isChapterLabelContext, string $paragraphStyle): void
    {
        if (! $isChapterLabelContext && $this->currentVerseNumber !== null) {
            $this->handleCharacterTag($item, $isParagraphBreak);
            return;
        }
        $skippedText = $this->extractTextFromItems($item['items'] ?? []);
        if ($skippedText !== '') {
            $reason = $isChapterLabelContext
                ? 'character content in chapter label paragraph (not inside a note)'
                : 'no current verse';
            $this->addWarning('ApiBibleContentParser: character content skipped.', [
                'context' => $this->parseContext,
                'paragraph_style' => $paragraphStyle,
                'reason' => $reason,
                'text_snippet' => $this->truncateForLog($skippedText),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function warnUnhandledItemIfNeeded(array $item, string $paragraphStyle): void
    {
        $itemName = $item['name'] ?? '(no name)';
        $itemType = $item['type'] ?? '(no type)';
        $hasNestedItems = ! empty($item['items']) && is_array($item['items']);
        if ($itemType !== 'tag' && ! $hasNestedItems) {
            return;
        }
        $this->addWarning('ApiBibleContentParser: unhandled item type, content may be lost.', [
            'context' => $this->parseContext,
            'paragraph_style' => $paragraphStyle,
            'item_name' => $itemName,
            'item_type' => $itemType,
            'has_nested_items' => $hasNestedItems,
        ]);
    }

    private function isVerseTag(string $itemName, string $itemType): bool
    {
        return $itemName === 'verse' && $itemType === 'tag';
    }

    private function handleVerseTag(array $item): void
    {
        $rawNumber = $item['attrs']['number'] ?? null;
        $verseNumber = $rawNumber !== null && $rawNumber !== '' && is_numeric($rawNumber)
            ? (int) $rawNumber
            : null;
        if ($verseNumber !== null) {
            $this->startVerse($verseNumber);
            return;
        }
        $this->addWarning('ApiBibleContentParser: verse tag ignored (missing or invalid number).', [
            'context' => $this->parseContext,
            'verse_attrs_number' => $rawNumber,
        ]);
    }

    private function handleTextItem(
        array $item,
        string $bookId,
        string $chapterNumber,
        bool $isParagraphBreak,
        bool $isChapterLabelContext,
        string $paragraphStyle = ''
    ): void {
        $verseId = $item['attrs']['verseId'] ?? null;
        $text = $item['text'] ?? '';

        if ($verseId === null || $text === '' || $isChapterLabelContext) {
            $this->warnSkippedTextIfNeeded($text, $paragraphStyle, $isChapterLabelContext ? 'text in chapter label paragraph (not inside a note)' : 'missing or invalid verseId');
            return;
        }

        $verseNumber = $this->parseVerseNumberFromVerseId($verseId, $bookId, $chapterNumber);
        if ($verseNumber === null) {
            $this->addWarning('ApiBibleContentParser: text skipped (verseId does not match chapter).', [
                'context' => $this->parseContext,
                'paragraph_style' => $paragraphStyle,
                'verse_id' => $verseId,
                'text_snippet' => $this->truncateForLog($text),
            ]);
            return;
        }

        $this->maybePrependParagraphBreak($verseNumber, $isParagraphBreak);
        $this->verseReceivedContentThisPara[$verseNumber] = true;
        $this->appendVerseText($verseNumber, $text);
        $this->currentVerseNumber = $verseNumber;
        $this->lastVerseInPara = $verseNumber;
    }

    private function warnSkippedTextIfNeeded(string $text, string $paragraphStyle, string $reason): void
    {
        if ($text === '') {
            return;
        }
        $this->addWarning('ApiBibleContentParser: text skipped.', [
            'context' => $this->parseContext,
            'paragraph_style' => $paragraphStyle,
            'reason' => $reason,
            'text_snippet' => $this->truncateForLog($text),
        ]);
    }

    private function truncateForLog(string $text, int $maxLength = 80): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength) . '…';
    }

    private function maybePrependParagraphBreak(int $verseNumber, bool $isParagraphBreak): void
    {
        if (! $isParagraphBreak || ! $this->verseHasContent($verseNumber)) {
            return;
        }
        if ($this->verseReceivedContentThisPara[$verseNumber] ?? false) {
            return;
        }
        $this->appendVerseText($verseNumber, "\n");
    }

    private function isNoteTag(string $itemName, string $itemType): bool
    {
        return $itemName === 'note' && $itemType === 'tag';
    }

    private function handleNoteTag(
        array $item,
        string $bookId,
        string $chapterNumber,
        bool $isChapterLabelContext
    ): void {
        $verseId = $item['attrs']['verseId'] ?? null;
        $noteStyle = $item['attrs']['style'] ?? '';
        if (! in_array($noteStyle, self::NOTE_STYLES, true) || $verseId === null) {
            return;
        }

        $verseNumber = $this->parseVerseNumberFromVerseId($verseId, $bookId, $chapterNumber);
        if ($verseNumber === null) {
            return;
        }

        $noteText = $this->extractNoteText($item['items'] ?? []);
        if ($noteText === '') {
            return;
        }

        $this->ensureVerseExists($verseNumber);
        $slug = $this->nextSlugForVerse($verseNumber);
        $this->versesData[$verseNumber]['references'][] = new VerseReferenceResponseDTO(
            slug: $slug,
            text: $noteText
        );

        $slugPlaceholder = '{{' . $slug . '}}';
        if ($isChapterLabelContext) {
            $this->versesData[$verseNumber]['ref_prefix'] .= $slugPlaceholder;
        } else {
            $this->versesData[$verseNumber]['text'] .= $slugPlaceholder;
        }
    }

    private function isCharacterTag(string $itemName, string $itemType): bool
    {
        return $itemName === 'char' && $itemType === 'tag';
    }

    /**
     * @param array<int, array<string, mixed>> $item
     */
    private function handleCharacterTag(array $item, bool $isParagraphBreak): void
    {
        $verseNumber = $this->currentVerseNumber;
        $this->maybePrependParagraphBreak($verseNumber, $isParagraphBreak);
        $this->verseReceivedContentThisPara[$verseNumber] = true;

        $text = $this->extractTextFromItems($item['items'] ?? []);
        if ($text !== '') {
            $this->appendVerseText($verseNumber, $text);
        }
    }

    private function verseHasContent(int $verseNumber): bool
    {
        if (! isset($this->versesData[$verseNumber])) {
            return false;
        }
        $verseData = $this->versesData[$verseNumber];

        return $verseData['text'] !== '' || $verseData['ref_prefix'] !== '';
    }

    private function startVerse(int $number): void
    {
        $this->ensureVerseExists($number);
        foreach ($this->titleBuffer as $title) {
            $this->versesData[$number]['titles'][] = $title;
        }
        $this->titleBuffer = [];
        $this->currentVerseNumber = $number;
        $this->lastVerseInPara = $number;
    }

    private function ensureVerseExists(int $number): void
    {
        if (! isset($this->versesData[$number])) {
            $this->versesData[$number] = [
                'titles' => [],
                'references' => [],
                'text' => '',
                'ref_prefix' => '',
            ];
            $this->verseSlugCount[$number] = 0;
        }
    }

    private function appendVerseText(int $verseNumber, string $text): void
    {
        $this->ensureVerseExists($verseNumber);
        $this->versesData[$verseNumber]['text'] .= $text;
    }

    private function nextSlugForVerse(int $verseNumber): string
    {
        $this->ensureVerseExists($verseNumber);
        $this->verseSlugCount[$verseNumber]++;

        return (string) $this->verseSlugCount[$verseNumber];
    }

    private function parseVerseNumberFromVerseId(string $verseId, string $bookId, string $chapterNumber): ?int
    {
        $prefix = $bookId . '.' . $chapterNumber . '.';
        if (! str_starts_with($verseId, $prefix)) {
            return null;
        }
        $num = substr($verseId, strlen($prefix));

        return is_numeric($num) ? (int) $num : null;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function extractTextFromItems(array $items): string
    {
        $parts = [];
        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'text') {
                $parts[] = $item['text'] ?? '';
            }
            if (($item['name'] ?? '') === 'char' && ($item['type'] ?? '') === 'tag') {
                $parts[] = $this->extractTextFromItems($item['items'] ?? []);
            }
        }

        return trim(implode('', $parts));
    }

    /**
     * Extract note content from char nodes with style ft, fqa, xt.
     * Logs when a note contains char with another style (e.g. fr, fv) so it can be added to NOTE_CONTENT_STYLES if needed.
     *
     * @param array<int, array<string, mixed>> $noteItems
     */
    private function extractNoteText(array $noteItems): string
    {
        $parts = [];
        foreach ($noteItems as $item) {
            if (($item['name'] ?? '') !== 'char' || ($item['type'] ?? '') !== 'tag') {
                continue;
            }
            $style = $item['attrs']['style'] ?? '';
            $text = $this->extractTextFromItems($item['items'] ?? []);
            if (in_array($style, self::NOTE_CONTENT_STYLES, true)) {
                $parts[] = $text;
            } elseif ($text !== '') {
                $this->addWarning('ApiBibleContentParser: note char style not used for reference text (add to NOTE_CONTENT_STYLES if needed).', [
                    'context' => $this->parseContext,
                    'char_style' => $style,
                    'text_snippet' => $this->truncateForLog($text),
                ]);
            }
        }

        return trim(implode(' ', $parts));
    }

    /**
     * @return Collection<int, VerseResponseDTO>
     */
    private function buildVerseDTOs(): Collection
    {
        ksort($this->versesData);
        $result = collect();
        foreach ($this->versesData as $number => $data) {
            $fullText = $data['ref_prefix'] . $data['text'];
            $result->push(new VerseResponseDTO(
                number: $number,
                text: trim($fullText),
                titles: collect($data['titles']),
                references: collect($data['references'])
            ));
        }

        return $result->values();
    }
}
