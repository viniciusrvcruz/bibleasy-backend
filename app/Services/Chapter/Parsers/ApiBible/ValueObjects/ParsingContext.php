<?php

namespace App\Services\Chapter\Parsers\ApiBible\ValueObjects;

readonly class ParsingContext
{
    public function __construct(
        public string $bookId,
        public string $chapterNumber,
        public bool $isParagraphBreak = false,
        public bool $isChapterLabel = false,
        public string $paragraphStyle = ''
    ) {}

    public function getContextKey(): string
    {
        return "{$this->bookId}.{$this->chapterNumber}";
    }
}
