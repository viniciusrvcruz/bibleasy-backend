<?php

namespace App\Services\Chapter\Parsers\ApiBible\Builders;

use App\Services\Chapter\DTOs\VerseReferenceResponseDTO;
use App\Services\Chapter\DTOs\VerseTitleDTO;

class VerseBuilder
{
    /** @var array<VerseTitleDTO> */
    public array $titles = [];

    /** @var array<VerseReferenceResponseDTO> */
    public array $references = [];

    public string $text = '';

    public string $refPrefix = '';

    private int $slugCount = 0;

    public function addTitle(VerseTitleDTO $title): void
    {
        $this->titles[] = $title;
    }

    public function addReference(VerseReferenceResponseDTO $reference): void
    {
        $this->references[] = $reference;
    }

    public function appendText(string $text): void
    {
        $this->text .= $text;
    }

    public function appendRefPrefix(string $text): void
    {
        $this->refPrefix .= $text;
    }

    public function nextSlug(): string
    {
        return (string) ++$this->slugCount;
    }

    public function hasContent(): bool
    {
        return $this->text !== '' || $this->refPrefix !== '';
    }

    public function getFullText(): string
    {
        return $this->refPrefix . $this->text;
    }
}
