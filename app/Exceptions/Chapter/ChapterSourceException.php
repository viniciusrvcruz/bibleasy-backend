<?php

namespace App\Exceptions\Chapter;

use App\Exceptions\CustomException;

class ChapterSourceException extends CustomException
{
    public function __construct(string $type, string $message, int $statusCode = 404)
    {
        parent::__construct($type, $message);
        $this->statusCode = $statusCode;
    }

    public static function chapterNotFound(string $message): self
    {
        return new self('chapter_not_found', $message, 404);
    }

    public static function externalApiError(string $message): self
    {
        return new self('external_api_error', $message, 502);
    }

    public static function invalidResponse(string $message): self
    {
        return new self('invalid_response', $message, 502);
    }
}
