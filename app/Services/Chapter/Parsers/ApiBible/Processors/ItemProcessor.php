<?php

namespace App\Services\Chapter\Parsers\ApiBible\Processors;

use App\Services\Chapter\DTOs\VerseReferenceResponseDTO;
use App\Services\Chapter\Parsers\ApiBible\Builders\ChapterVerseBuilder;
use App\Services\Chapter\Parsers\ApiBible\Enums\ItemTypeEnum;
use App\Services\Chapter\Parsers\ApiBible\TitleBuffer;
use App\Services\Chapter\Parsers\ApiBible\ValueObjects\ParsingContext;
use App\Services\Chapter\Parsers\ApiBible\WarningCollector;

class ItemProcessor
{
    private const NOTE_STYLES = ['f', 'fe', 'x', 'ef', 'ex'];
    private const NOTE_CONTENT_STYLES = ['ft', 'fqa', 'xt', 'fr', 'fq'];
    private const PLACEHOLDER_FORMAT = '{{%s}}';
    private const LOG_TRUNCATE_LENGTH = 50;

    private ?int $currentVerseNumber = null;

    /** @var array<int, bool> */
    private array $verseReceivedContentThisPara = [];

    private bool $isFirstTextInParagraph = false;
    private ?int $lastVerseInParagraph = null;

    public function __construct(
        private readonly ChapterVerseBuilder $builder,
        private readonly TitleBuffer $titleBuffer,
        private readonly WarningCollector $warnings
    ) {}

    public function addParagraphStart(): void
    {
        $this->isFirstTextInParagraph = true;
    }

    public function addParagraphEnd(): void
    {
        if ($this->lastVerseInParagraph !== null) {
            $verseData = $this->builder->getOrCreate($this->lastVerseInParagraph);
            if (!str_ends_with($verseData->getFullText(), "\n")) {
                $verseData->appendText("\n");
            }
            $this->lastVerseInParagraph = null;
        }
    }

    /**
     * Appends a newline to the last verse that has content (e.g. for blank paragraph style "b").
     */
    public function addBlankLine(): void
    {
        $verseNumber = $this->builder->getLastVerseNumberWithContent();
        if ($verseNumber !== null) {
            $this->builder->getOrCreate($verseNumber)->appendText("\n");
        }
    }

    public function processItems(array $items, ParsingContext $context): void
    {
        $this->verseReceivedContentThisPara = [];

        foreach ($items as $item) {
            $itemType = ItemTypeEnum::fromItem($item);

            match ($itemType) {
                ItemTypeEnum::VERSE => $this->handleVerse($item, $context),
                ItemTypeEnum::TEXT => $this->handleText($item, $context),
                ItemTypeEnum::NOTE => $this->handleNote($item, $context),
                ItemTypeEnum::CHAR => $this->handleChar($item, $context),
                ItemTypeEnum::UNKNOWN => $this->handleUnknown($item, $context),
            };
        }
    }

    public function extractTextFromItems(array $items): string
    {
        $parts = [];
        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'text') {
                $parts[] = $item['text'] ?? '';
            }
            $name = $item['name'] ?? '';
            if (($name === 'char' || $name === 'ref') && ($item['type'] ?? '') === 'tag') {
                $parts[] = $this->extractTextFromItems($item['items'] ?? []);
            }
        }

        return implode('', $parts);
    }

    /**
     * Builds section/reference title text from items, processing notes as placeholders (e.g. {{1}})
     * and adding the note content as references to the verse indicated by the note's verseId.
     */
    public function buildTitleTextWithNotePlaceholders(array $items, ParsingContext $context): string
    {
        $result = '';
        foreach ($items as $item) {
            $itemType = ItemTypeEnum::fromItem($item);
            if ($itemType === ItemTypeEnum::TEXT) {
                $result .= $item['text'] ?? '';
                continue;
            }
            if ($itemType === ItemTypeEnum::NOTE) {
                $placeholder = $this->processNoteForTitle($item, $context);
                $result .= $placeholder ?? '';
                continue;
            }
            $result .= $this->extractTextFromItems([$item]);
        }

        return $result;
    }

    /**
     * Processes a note item inside a title paragraph: adds the reference to the verse from note's verseId,
     * returns the placeholder string (e.g. "{{1}}") to be inserted in the title text.
     */
    public function processNoteForTitle(array $noteItem, ParsingContext $context): ?string
    {
        $verseId = $noteItem['attrs']['verseId'] ?? null;
        $noteStyle = $noteItem['attrs']['style'] ?? '';

        if (!in_array($noteStyle, self::NOTE_STYLES, true) || $verseId === null) {
            return null;
        }

        $verseNumber = $this->parseVerseNumber($verseId, $context);
        if ($verseNumber === null) {
            return null;
        }

        $noteText = $this->extractNoteText($noteItem['items'] ?? [], $context);
        if ($noteText === '') {
            return null;
        }

        $verseData = $this->builder->getOrCreate($verseNumber);
        $slug = $verseData->nextSlug();
        $verseData->addReference(new VerseReferenceResponseDTO($slug, $noteText));

        return sprintf(self::PLACEHOLDER_FORMAT, $slug);
    }

    private function handleVerse(array $item, ParsingContext $context): void
    {
        $rawNumber = $item['attrs']['number'] ?? null;
        $verseNumber = $rawNumber !== null && $rawNumber !== '' && is_numeric($rawNumber)
            ? (int) $rawNumber
            : null;

        if ($verseNumber === null) {
            $this->warnings->add('ApiBibleContentParser: verse tag ignored (missing or invalid number).', [
                'context' => $context->getContextKey(),
                'verse_attrs_number' => $rawNumber,
            ]);
            return;
        }

        $verseData = $this->builder->getOrCreate($verseNumber);

        foreach ($this->titleBuffer->flush() as $title) {
            $verseData->addTitle($title);
        }

        $this->currentVerseNumber = $verseNumber;
    }

    private function handleText(array $item, ParsingContext $context): void
    {
        $verseId = $item['attrs']['verseId'] ?? null;
        $text = $item['text'] ?? '';

        if ($context->isChapterLabel) {
            // Chapter label text is intentionally not added to verse content; do not warn.
            return;
        }

        if ($verseId === null || $text === '') {
            $this->warnSkippedText($text, $context);
            return;
        }

        $verseNumber = $this->parseVerseNumber($verseId, $context);
        if ($verseNumber === null) {
            $this->warnings->add('ApiBibleContentParser: text skipped (verseId does not match chapter).', [
                'context' => $context->getContextKey(),
                'paragraph_style' => $context->paragraphStyle,
                'verse_id' => $verseId,
                'text_snippet' => $this->truncate($text),
            ]);
            return;
        }

        $verseData = $this->builder->getOrCreate($verseNumber);
        $this->maybePrependParagraphBreak($verseData, $verseNumber, $context);

        // Only add newline before this segment when it's the first text in this paragraph
        // and the verse already has content from a previous paragraph (without trailing newline).
        if ($this->isFirstTextInParagraph && $verseData->hasContent() && !str_ends_with($verseData->getFullText(), "\n")) {
            $verseData->appendText("\n");
        }
        $this->isFirstTextInParagraph = false;

        $this->verseReceivedContentThisPara[$verseNumber] = true;
        $verseData->appendText($text);
        $this->currentVerseNumber = $verseNumber;
        $this->lastVerseInParagraph = $verseNumber;
    }

    private function handleNote(array $item, ParsingContext $context): void
    {
        $verseId = $item['attrs']['verseId'] ?? null;
        $noteStyle = $item['attrs']['style'] ?? '';

        if (!in_array($noteStyle, self::NOTE_STYLES, true) || $verseId === null) {
            return;
        }

        $verseNumber = $this->parseVerseNumber($verseId, $context);
        if ($verseNumber === null) {
            return;
        }

        $noteText = $this->extractNoteText($item['items'] ?? [], $context);
        if ($noteText === '') {
            return;
        }

        $verseData = $this->builder->getOrCreate($verseNumber);
        $slug = $verseData->nextSlug();
        $verseData->addReference(new VerseReferenceResponseDTO($slug, $noteText));

        $placeholder = sprintf(self::PLACEHOLDER_FORMAT, $slug);
        if ($context->isChapterLabel) {
            $verseData->appendRefPrefix($placeholder);
        } else {
            $verseData->appendText($placeholder);
            $this->isFirstTextInParagraph = false;
            $this->lastVerseInParagraph = $verseNumber;
        }
    }

    private function handleChar(array $item, ParsingContext $context): void
    {
        if (!$context->isChapterLabel && $this->currentVerseNumber !== null) {
            $verseData = $this->builder->getOrCreate($this->currentVerseNumber);
            $this->maybePrependParagraphBreak($verseData, $this->currentVerseNumber, $context);
            $this->verseReceivedContentThisPara[$this->currentVerseNumber] = true;

            $text = $this->extractTextFromItems($item['items'] ?? []);
            if ($text !== '') {
                $verseData->appendText($text);
                $this->lastVerseInParagraph = $this->currentVerseNumber;
            }
            return;
        }

        $skippedText = $this->extractTextFromItems($item['items'] ?? []);
        if ($skippedText !== '') {
            $reason = $context->isChapterLabel
                ? 'character content in chapter label paragraph (not inside a note)'
                : 'no current verse';

            $this->warnings->add('ApiBibleContentParser: character content skipped.', [
                'context' => $context->getContextKey(),
                'paragraph_style' => $context->paragraphStyle,
                'reason' => $reason,
                'text_snippet' => $this->truncate($skippedText),
            ]);
        }
    }

    private function handleUnknown(array $item, ParsingContext $context): void
    {
        $itemName = $item['name'] ?? '(no name)';
        $itemType = $item['type'] ?? '(no type)';
        $hasNestedItems = !empty($item['items']) && is_array($item['items']);

        if ($itemType !== 'tag' && !$hasNestedItems) {
            return;
        }

        $this->warnings->add('ApiBibleContentParser: unhandled item type, content may be lost.', [
            'context' => $context->getContextKey(),
            'paragraph_style' => $context->paragraphStyle,
            'item_name' => $itemName,
            'item_type' => $itemType,
            'has_nested_items' => $hasNestedItems,
        ]);
    }

    private function extractNoteText(array $noteItems, ParsingContext $context): string
    {
        $result = '';
        foreach ($noteItems as $item) {
            if (($item['name'] ?? '') !== 'char' || ($item['type'] ?? '') !== 'tag') {
                continue;
            }

            $style = $item['attrs']['style'] ?? '';
            $text = $this->extractTextFromItems($item['items'] ?? []);

            if (in_array($style, self::NOTE_CONTENT_STYLES, true)) {
                $result .= $text;
            } elseif ($text !== '') {
                $this->warnings->add('ApiBibleContentParser: note char style not used for reference text (add to NOTE_CONTENT_STYLES if needed).', [
                    'context' => $context->getContextKey(),
                    'char_style' => $style,
                    'text_snippet' => $this->truncate($text),
                ]);
            }
        }

        return trim($result);
    }

    private function parseVerseNumber(string $verseId, ParsingContext $context): ?int
    {
        $prefix = "{$context->bookId}.{$context->chapterNumber}.";
        if (!str_starts_with($verseId, $prefix)) {
            return null;
        }

        $num = substr($verseId, strlen($prefix));
        return is_numeric($num) ? (int) $num : null;
    }

    private function maybePrependParagraphBreak(object $verseData, int $verseNumber, ParsingContext $context): void
    {
        if (!$context->isParagraphBreak || !$verseData->hasContent()) {
            return;
        }

        if ($this->verseReceivedContentThisPara[$verseNumber] ?? false) {
            return;
        }

        if (!str_ends_with($verseData->getFullText(), "\n")) {
            $verseData->appendText("\n");
        }
    }

    private function warnSkippedText(string $text, ParsingContext $context): void
    {
        if ($text === '') {
            return;
        }

        $reason = $context->isChapterLabel
            ? 'text in chapter label paragraph (not inside a note)'
            : 'missing or invalid verseId';

        $this->warnings->add('ApiBibleContentParser: text skipped.', [
            'context' => $context->getContextKey(),
            'paragraph_style' => $context->paragraphStyle,
            'reason' => $reason,
            'text_snippet' => $this->truncate($text),
        ]);
    }

    private function truncate(string $text, int $maxLength = self::LOG_TRUNCATE_LENGTH): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength) . '…';
    }
}
