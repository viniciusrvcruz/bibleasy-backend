<?php

namespace App\Services\Version\DTOs;

class FileDTO
{
    public function __construct(
        public readonly string $content,
        public readonly string $fileName,
        public readonly string $extension,
    ) {}
}

