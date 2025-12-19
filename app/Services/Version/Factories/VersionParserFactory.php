<?php

namespace App\Services\Version\Factories;

use App\Services\Version\Interfaces\VersionParserInterface;
use App\Services\Version\Parsers\JsonThiagoBodrukParser;
use App\Exceptions\Version\VersionImportException;

class VersionParserFactory
{
    private static array $parsers = [
        'json_thiago_bodruk' => JsonThiagoBodrukParser::class,
    ];

    public static function make(string $format): VersionParserInterface
    {
        $parserClass = self::$parsers[$format] ?? null;

        if (!$parserClass) {
            throw new VersionImportException('parser_not_found', "Parser for format '{$format}' not found");
        }

        return app($parserClass);
    }

    public static function getAvailableFormats(): array
    {
        return array_keys(self::$parsers);
    }
}
