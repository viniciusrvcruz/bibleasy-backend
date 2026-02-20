<?php

namespace App\Services\Chapter\Parsers;

use App\Services\Chapter\Parsers\ApiBible\Builders\VerseDTOBuilder;
use App\Services\Chapter\Parsers\ApiBible\Processors\ItemProcessor;
use App\Services\Chapter\Parsers\ApiBible\Processors\ParagraphProcessor;
use App\Services\Chapter\Parsers\ApiBible\TitleBuffer;
use App\Services\Chapter\Parsers\ApiBible\ValueObjects\ParsingContext;
use App\Services\Chapter\Parsers\ApiBible\WarningCollector;
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
        private readonly VerseDTOBuilder $builder
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

        $this->warnings->flush();

        return $this->builder->build();
    }
}
