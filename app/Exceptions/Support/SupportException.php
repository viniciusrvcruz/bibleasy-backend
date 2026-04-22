<?php

namespace App\Exceptions\Support;

use App\Exceptions\CustomException;

class SupportException extends CustomException
{
    public function __construct(string $type, string $message, int $statusCode = 500)
    {
        parent::__construct($type, $message);
        $this->statusCode = $statusCode;
    }

    public static function missingConfiguration(string $message): self
    {
        return new self('missing_configuration', $message, 500);
    }

    public static function externalApiError(string $message): self
    {
        return new self('external_api_error', $message, 502);
    }

    public static function resourceNotFound(string $message): self
    {
        return new self('resource_not_found', $message, 422);
    }
}
