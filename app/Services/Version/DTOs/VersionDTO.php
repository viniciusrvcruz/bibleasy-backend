<?php

namespace App\Services\Version\DTOs;

use Illuminate\Support\Collection;

class VersionDTO
{
    /**
     * @param Collection<int, BookDTO> $books
     */
    public function __construct(
        public readonly Collection $books,
    ) {}
}
