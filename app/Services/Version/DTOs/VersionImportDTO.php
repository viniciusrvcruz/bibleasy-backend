<?php

namespace App\Services\Version\DTOs;

use App\Enums\VersionTextSourceEnum;

class VersionImportDTO
{
    /**
     * @param array<int, FileDTO> $files
     */
    public function __construct(
        public readonly array $files,
        public readonly string $adapterName,
        public readonly string $versionAbbreviation,
        public readonly string $versionName,
        public readonly string $language,
        public readonly string $copyright,
        public readonly VersionTextSourceEnum $textSource,
        public readonly ?string $externalVersionId = null,
        public readonly ?int $cacheTtl = null,
    ) {}
}
