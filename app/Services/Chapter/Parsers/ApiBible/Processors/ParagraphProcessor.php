<?php

namespace App\Services\Chapter\Parsers\ApiBible\Processors;

use App\Enums\VerseTitleTypeEnum;
use App\Services\Chapter\DTOs\VerseTitleDTO;
use App\Services\Chapter\Parsers\ApiBible\TitleBuffer;
use App\Services\Chapter\Parsers\ApiBible\ValueObjects\ParsingContext;
use App\Services\Chapter\Parsers\ApiBible\WarningCollector;
use App\Services\Version\Adapters\Usfm\UsfmMarkers;

class ParagraphProcessor
{
    private const CHAPTER_LABEL_STYLE = 'cl';
    private const BLANK_PARAGRAPH_STYLE = 'b';
    private const SECTION_TITLE_STYLES = ['d', 's', 's1', 's2', 's3', 'qa', 'ms', 'ms1', 'ms2', 'ms3'];
    private const REFERENCE_TITLE_STYLES = ['r', 'mr'];

    public function __construct(
        private readonly TitleBuffer $titleBuffer,
        private readonly ItemProcessor $itemProcessor,
        private readonly WarningCollector $warnings
    ) {}

    public function process(array $paragraph, ParsingContext $baseContext): void
    {
        if (!$this->isParagraphTag($paragraph)) {
            $name = $paragraph['name'] ?? '(no name)';
            $type = $paragraph['type'] ?? '(no type)';
            $this->warnings->add('ApiBibleContentParser: non-para content skipped (content may be lost).', [
                'context' => $baseContext->getContextKey(),
                'item_name' => $name,
                'item_type' => $type,
            ]);
            return;
        }

        $style = $paragraph['attrs']['style'] ?? '';
        $items = $paragraph['items'] ?? [];

        if ($style === self::CHAPTER_LABEL_STYLE) {
            $this->processChapterLabel($items, $baseContext, $style);
            return;
        }

        if ($this->isSectionTitle($style)) {
            $this->processSectionTitle($items, $baseContext);
            return;
        }

        if ($this->isReferenceTitle($style)) {
            $this->processReferenceTitle($items, $baseContext);
            return;
        }

        if ($style === self::BLANK_PARAGRAPH_STYLE) {
            $this->itemProcessor->addBlankLine();
            return;
        }

        if ($style !== '' && !$this->isKnownStyle($style)) {
            $this->warnings->add('ApiBibleContentParser: unknown paragraph style, content may be lost.', [
                'context' => $baseContext->getContextKey(),
                'style' => $style,
            ]);
        }

        $isParagraphBreak = in_array($style, UsfmMarkers::PARAGRAPH_BREAK_MARKERS, true);

        $context = new ParsingContext(
            bookId: $baseContext->bookId,
            chapterNumber: $baseContext->chapterNumber,
            isParagraphBreak: $isParagraphBreak,
            isChapterLabel: false,
            paragraphStyle: $style
        );

        $this->itemProcessor->addParagraphStart();
        $this->itemProcessor->processItems($items, $context);
        $this->itemProcessor->addParagraphEnd();
    }

    private function isParagraphTag(array $para): bool
    {
        return ($para['name'] ?? '') === 'para' && ($para['type'] ?? '') === 'tag';
    }

    private function isSectionTitle(string $style): bool
    {
        return in_array($style, self::SECTION_TITLE_STYLES, true);
    }

    private function isReferenceTitle(string $style): bool
    {
        return in_array($style, self::REFERENCE_TITLE_STYLES, true);
    }

    private function isKnownStyle(string $style): bool
    {
        return in_array($style, array_merge(
            [self::CHAPTER_LABEL_STYLE, self::BLANK_PARAGRAPH_STYLE],
            self::SECTION_TITLE_STYLES,
            self::REFERENCE_TITLE_STYLES,
            UsfmMarkers::PARAGRAPH_BREAK_MARKERS
        ), true);
    }

    private function processChapterLabel(array $items, ParsingContext $baseContext, string $style): void
    {
        $context = new ParsingContext(
            $baseContext->bookId,
            $baseContext->chapterNumber,
            false,
            true,
            $style
        );

        $this->itemProcessor->processItems($items, $context);
    }

    private function processSectionTitle(array $items, ParsingContext $baseContext): void
    {
        $context = new ParsingContext(
            $baseContext->bookId,
            $baseContext->chapterNumber,
            false,
            false,
            ''
        );
        $text = $this->itemProcessor->buildTitleTextWithNotePlaceholders($items, $context);
        if ($text !== '') {
            $this->titleBuffer->add(new VerseTitleDTO($text, VerseTitleTypeEnum::SECTION));
        }
    }

    private function processReferenceTitle(array $items, ParsingContext $baseContext): void
    {
        $context = new ParsingContext(
            $baseContext->bookId,
            $baseContext->chapterNumber,
            false,
            false,
            ''
        );
        $text = $this->itemProcessor->buildTitleTextWithNotePlaceholders($items, $context);
        if ($text !== '') {
            $this->titleBuffer->add(new VerseTitleDTO($text, VerseTitleTypeEnum::REFERENCE));
        }
    }
}
