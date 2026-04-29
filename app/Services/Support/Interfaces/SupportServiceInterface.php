<?php

namespace App\Services\Support\Interfaces;

use App\Services\Support\DTOs\SendSupportDTO;

interface SupportServiceInterface
{
    public function send(SendSupportDTO $dto): bool;
}
