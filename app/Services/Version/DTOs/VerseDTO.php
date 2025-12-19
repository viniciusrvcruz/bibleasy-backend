<?php

namespace App\Services\Version\DTOs;

class VerseDTO
{
    public function __construct(
        public readonly int $number,
        public readonly string $text,
    ) {}
}
