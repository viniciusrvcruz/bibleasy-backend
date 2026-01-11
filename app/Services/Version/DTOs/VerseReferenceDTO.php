<?php

namespace App\Services\Version\DTOs;

class VerseReferenceDTO
{
    public function __construct(
        public readonly string $slug,
        public readonly string $text,
    ) {}
}
