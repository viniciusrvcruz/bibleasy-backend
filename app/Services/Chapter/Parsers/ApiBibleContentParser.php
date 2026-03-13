<?php

namespace App\Services\Chapter\Parsers;

use App\Services\Chapter\Parsers\ApiBible\Builders\ChapterVerseBuilder;
use App\Services\Chapter\Parsers\ApiBible\Processors\ItemProcessor;
use App\Services\Chapter\Parsers\ApiBible\Processors\ParagraphProcessor;
use App\Services\Chapter\Parsers\ApiBible\TitleBuffer;
use App\Services\Chapter\Parsers\ApiBible\ValueObjects\ParsingContext;
use App\Services\Chapter\Parsers\ApiBible\WarningCollector;
use App\Services\Chapter\Validators\ReferenceSlugValidator;
use App\Enums\VerseTitlePositionEnum;
use Illuminate\Support\Collection;

/**
 * Parses api.bible chapter content array into verse DTOs.
 * Refactored version following SOLID principles with separated concerns.
 */
class ApiBibleContentParser
{
    private readonly ItemProcessor $itemProcessor;
    private readonly ParagraphProcessor $paragraphProcessor;

    public function __construct(
        private readonly WarningCollector $warnings,
        private readonly TitleBuffer $titleBuffer,
        private readonly ChapterVerseBuilder $builder,
        private readonly ReferenceSlugValidator $referenceSlugValidator
    )
    {
        $this->itemProcessor = new ItemProcessor($this->builder, $this->titleBuffer, $this->warnings);
        $this->paragraphProcessor = new ParagraphProcessor($this->titleBuffer, $this->itemProcessor, $this->warnings);
    }

    /**
     * @return Collection<int, VerseResponseDTO>
     */
    public function parse(array $content, string $bookId, string $chapterNumber): Collection
    {
        $context = new ParsingContext($bookId, $chapterNumber);

        foreach ($content as $paragraph) {
            $this->paragraphProcessor->process($paragraph, $context);
        }

        // Flush any remaining titles to the last verse as end
        $lastVerseNumber = $this->builder->getLastVerseNumberWithContent();
        if ($lastVerseNumber !== null && !$this->titleBuffer->isEmpty()) {
            $this->itemProcessor->addTitlesToVerse(
                $this->titleBuffer->flush(),
                $lastVerseNumber,
                VerseTitlePositionEnum::END
            );
        }

        $verses = $this->builder->build();

        $this->collectReferenceSlugWarnings($verses, $bookId, $chapterNumber);
        $this->warnings->flush();

        return $verses;
    }

    /**
     * Collects reference-slug validation warnings and adds them to the warning collector.
     *
     * @param  Collection<int, \App\Services\Chapter\DTOs\VerseResponseDTO>  $verses
     */
    private function collectReferenceSlugWarnings(Collection $verses, string $bookId, string $chapterNumber): void
    {
        foreach ($this->referenceSlugValidator->getWarnings($verses, $bookId, $chapterNumber) as $warning) {
            $this->warnings->add($warning['message'], $warning['context']);
        }
    }
}
