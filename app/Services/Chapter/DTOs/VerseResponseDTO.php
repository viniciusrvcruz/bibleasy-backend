<?php

namespace App\Services\Chapter\DTOs;

use Illuminate\Support\Collection;

class VerseResponseDTO
{
    /**
     * @param  Collection<int, VerseTitleDTO>  $titles
     * @param  Collection<int, VerseReferenceResponseDTO>  $references
     */
    public function __construct(
        public readonly int $number,
        public readonly string $text,
        public readonly Collection $titles,
        public readonly Collection $references,
    ) {}
}
