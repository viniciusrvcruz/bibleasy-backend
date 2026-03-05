<?php

namespace App\Services\Chapter\DTOs;

use App\Enums\BookAbbreviationEnum;
use Illuminate\Support\Collection;

class ChapterResponseDTO
{
    /**
     * @param  Collection<int, VerseResponseDTO>  $verses
     */
    public function __construct(
        public readonly int $number,
        public readonly string $bookName,
        public readonly BookAbbreviationEnum $bookAbbreviation,
        public readonly Collection $verses,
    ) {}
}
