<?php

namespace App\Services\Version\DTOs;

class VersionImportDTO
{
    public function __construct(
        public readonly string $content,
        public readonly string $importerName,
        public readonly string $versionName,
        public readonly string $versionFullName,
        public readonly string $language,
        public readonly string $copyright,
        public readonly string $fileExtension,
    ) {}
}
