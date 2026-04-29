<?php

namespace App\Services\Support\DTOs;

use App\Enums\Support\SupportTypeEnum;
use Illuminate\Http\UploadedFile;

class SendSupportDTO
{
    /**
     * @param array<int, UploadedFile> $files
     */
    public function __construct(
        public readonly SupportTypeEnum $type,
        public readonly string $description,
        public readonly array $files,
        public readonly string $userAgent,
        public readonly string $ip,
        public readonly ?string $email = null,
    ) {}
}
