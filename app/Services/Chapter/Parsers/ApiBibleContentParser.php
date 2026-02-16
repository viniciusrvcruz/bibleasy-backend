<?php

namespace App\Services\Chapter\Parsers;

use App\Enums\VerseTitleTypeEnum;
use App\Services\Chapter\DTOs\VerseReferenceResponseDTO;
use App\Services\Chapter\DTOs\VerseResponseDTO;
use App\Services\Chapter\DTOs\VerseTitleDTO;
use App\Services\Version\Adapters\Usfm\UsfmMarkers;
use Illuminate\Support\Collection;

/**
 * Parses api.bible chapter content array into verse DTOs.
 * Handles section/reference titles, verse text, notes (footnotes/cross-refs), and paragraph breaks.
 */
class ApiBibleContentParser
{
    private const SECTION_TITLE_STYLES = ['d', 's1', 's2', 's3', 'qa'];

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

    /** @var array<int, bool> verses that received any content in the current para (avoid \n between items in same para) */
    private array $verseReceivedContentThisPara = [];

    /**
     * @return Collection<int, VerseResponseDTO>
     */
    public function parse(array $content, string $bookId, string $chapterNumber): Collection
    {
        $this->reset();

        foreach ($content as $para) {
            if (($para['name'] ?? '') !== 'para' || ($para['type'] ?? '') !== 'tag') {
                continue;
            }

            $style = $para['attrs']['style'] ?? '';
            $items = $para['items'] ?? [];

            if ($style === 'cl') {
                $this->processClPara($items, $bookId, $chapterNumber);
                continue;
            }

            if (in_array($style, self::SECTION_TITLE_STYLES, true)) {
                $text = $this->extractTextFromItems($items);
                if ($text !== '') {
                    $this->titleBuffer[] = new VerseTitleDTO($text, VerseTitleTypeEnum::SECTION);
                }
                $this->lastParaStyle = $style;
                continue;
            }

            if (in_array($style, self::REFERENCE_TITLE_STYLES, true)) {
                $text = $this->extractTextFromItems($items);
                if ($text !== '') {
                    $this->titleBuffer[] = new VerseTitleDTO($text, VerseTitleTypeEnum::REFERENCE);
                }
                $this->lastParaStyle = $style;
                continue;
            }

            $isParagraphBreak = in_array($style, UsfmMarkers::PARAGRAPH_BREAK_MARKERS, true);
            $this->processParaItems($items, $bookId, $chapterNumber, $isParagraphBreak);
            $this->lastParaStyle = $style;
        }

        return $this->buildVerseDTOs();
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
    }

    /**
     * Chapter label: ignore as title, only process notes (assign to verse by verseId).
     *
     * @param array<int, array<string, mixed>> $items
     */
    private function processClPara(array $items, string $bookId, string $chapterNumber): void
    {
        $this->processItems($items, $bookId, $chapterNumber, false, true);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function processParaItems(array $items, string $bookId, string $chapterNumber, bool $isParagraphBreak): void
    {
        $this->verseReceivedContentThisPara = [];
        $this->processItems($items, $bookId, $chapterNumber, $isParagraphBreak, false);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function processItems(
        array $items,
        string $bookId,
        string $chapterNumber,
        bool $isParagraphBreak,
        bool $isClContext
    ): void {
        foreach ($items as $item) {
            $type = $item['type'] ?? '';
            $name = $item['name'] ?? '';

            if ($name === 'verse' && $type === 'tag') {
                $num = isset($item['attrs']['number']) ? (int) $item['attrs']['number'] : null;
                if ($num !== null) {
                    $this->startVerse($num);
                }
                continue;
            }

            if ($type === 'text') {
                $verseId = $item['attrs']['verseId'] ?? null;
                $text = $item['text'] ?? '';
                if ($verseId !== null && $text !== '' && ! $isClContext) {
                    $verseNum = $this->parseVerseNumberFromVerseId($verseId, $bookId, $chapterNumber);
                    if ($verseNum !== null) {
                        if ($isParagraphBreak && $this->verseHasContent($verseNum) && ! ($this->verseReceivedContentThisPara[$verseNum] ?? false)) {
                            $this->appendVerseText($verseNum, "\n");
                        }
                        $this->verseReceivedContentThisPara[$verseNum] = true;
                        $this->appendVerseText($verseNum, $text);
                        $this->currentVerseNumber = $verseNum;
                        $this->lastVerseInPara = $verseNum;
                    }
                }
                continue;
            }

            if ($name === 'note' && $type === 'tag') {
                $verseId = $item['attrs']['verseId'] ?? null;
                $noteStyle = $item['attrs']['style'] ?? '';
                if (in_array($noteStyle, self::NOTE_STYLES, true) && $verseId !== null) {
                    $verseNum = $this->parseVerseNumberFromVerseId($verseId, $bookId, $chapterNumber);
                    if ($verseNum !== null) {
                        $noteText = $this->extractNoteText($item['items'] ?? []);
                        if ($noteText !== '') {
                            $slug = $this->nextSlugForVerse($verseNum);
                            $this->ensureVerseExists($verseNum);
                            $this->versesData[$verseNum]['references'][] = new VerseReferenceResponseDTO(
                                slug: $slug,
                                text: $noteText
                            );
                            if ($isClContext) {
                                $this->versesData[$verseNum]['ref_prefix'] .= '{{' . $slug . '}}';
                            } else {
                                $this->versesData[$verseNum]['text'] .= '{{' . $slug . '}}';
                            }
                        }
                    }
                }
                continue;
            }

            if ($name === 'char' && $type === 'tag' && ! $isClContext && $this->currentVerseNumber !== null) {
                $verseNum = $this->currentVerseNumber;
                if ($isParagraphBreak && $this->verseHasContent($verseNum) && ! ($this->verseReceivedContentThisPara[$verseNum] ?? false)) {
                    $this->appendVerseText($verseNum, "\n");
                }
                $this->verseReceivedContentThisPara[$verseNum] = true;
                $text = $this->extractTextFromItems($item['items'] ?? []);
                if ($text !== '') {
                    $this->appendVerseText($verseNum, $text);
                }
            }
        }
    }

    private function verseHasContent(int $verseNum): bool
    {
        if (! isset($this->versesData[$verseNum])) {
            return false;
        }
        $d = $this->versesData[$verseNum];

        return $d['text'] !== '' || $d['ref_prefix'] !== '';
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

    private function appendVerseText(int $verseNum, string $text): void
    {
        $this->ensureVerseExists($verseNum);
        $this->versesData[$verseNum]['text'] .= $text;
    }

    private function nextSlugForVerse(int $verseNum): string
    {
        $this->ensureVerseExists($verseNum);
        $this->verseSlugCount[$verseNum]++;

        return (string) $this->verseSlugCount[$verseNum];
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
            if (in_array($style, self::NOTE_CONTENT_STYLES, true)) {
                $parts[] = $this->extractTextFromItems($item['items'] ?? []);
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
