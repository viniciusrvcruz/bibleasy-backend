<?php

namespace App\Services\Chapter\DTOs;

class VerseReferenceResponseDTO
{
    public function __construct(
        public readonly string $slug,
        public readonly string $text,
    ) {}
}
