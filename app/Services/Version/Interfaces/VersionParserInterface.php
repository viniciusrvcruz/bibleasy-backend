<?php

namespace App\Services\Version\Interfaces;

use App\Services\Version\DTOs\FileDTO;
use App\Services\Version\DTOs\VersionDTO;

interface VersionParserInterface
{
    /**
     * @param array<int, FileDTO> $files
     */
    public function parse(array $files): VersionDTO;
}
