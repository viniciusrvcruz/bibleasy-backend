<?php

namespace App\Services\Version\DTOs;

use Illuminate\Support\Collection;

class ChapterDTO
{
    /**
     * @param Collection<int, VerseDTO> $verses
     */
    public function __construct(
        public readonly int $number,
        public readonly Collection $verses,
    ) {}
}
