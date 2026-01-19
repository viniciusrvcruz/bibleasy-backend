<?php

namespace App\Services\Version\DTOs;

use Illuminate\Support\Collection;

class VerseDTO
{
    /**
     * @param Collection<int, VerseReferenceDTO> $references
     */
    public function __construct(
        public readonly int $number,
        public readonly string $text,
        public readonly Collection $references = new Collection(),
    ) {}
}
