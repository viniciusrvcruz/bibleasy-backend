<?php

namespace App\Services\Chapter\DTOs;

use App\Enums\VerseTitleTypeEnum;

class VerseTitleDTO
{
    public function __construct(
        public readonly string $text,
        public readonly VerseTitleTypeEnum $type,
    ) {}
}
