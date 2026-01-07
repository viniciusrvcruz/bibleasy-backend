<?php

namespace App\Services\Version\DTOs;

class VersionImportDTO
{
    /**
     * @param array<int, FileDTO> $files
     */
    public function __construct(
        public readonly array $files,
        public readonly string $importerName,
        public readonly string $versionAbbreviation,
        public readonly string $versionName,
        public readonly string $language,
        public readonly string $copyright,
    ) {}
}
