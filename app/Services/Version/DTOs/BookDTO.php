<?php

namespace App\Services\Version\DTOs;

use Illuminate\Support\Collection;

class BookDTO
{
    /**
     * @param Collection<int, ChapterDTO> $chapters
     */
    public function __construct(
        public readonly string $name,
        public readonly Collection $chapters,
    ) {}
}
