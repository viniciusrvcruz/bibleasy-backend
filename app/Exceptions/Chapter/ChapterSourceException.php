<?php

namespace App\Exceptions\Chapter;

use App\Exceptions\CustomException;

class ChapterSourceException extends CustomException
{
    private const STATUS_MAP = [
        'chapter_not_found' => 404,
        'external_api_error' => 502,
        'invalid_response' => 502,
    ];

    public function __construct(string $type, string $message)
    {
        parent::__construct($type, $message);
        $this->statusCode = self::STATUS_MAP[$type] ?? 404;
    }
}
