<?php

namespace App\Services\Version\Interfaces;

use App\Services\Version\DTOs\VersionDTO;

interface VersionParserInterface
{
    public function parse(string $content): VersionDTO;
}
