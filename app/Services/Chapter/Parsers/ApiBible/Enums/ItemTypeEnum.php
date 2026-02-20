<?php

namespace App\Services\Chapter\Parsers\ApiBible\Enums;

enum ItemTypeEnum
{
    case VERSE;
    case NOTE;
    case CHAR;
    case TEXT;
    case UNKNOWN;

    public static function fromItem(array $item): self
    {
        $name = $item['name'] ?? '';
        $type = $item['type'] ?? '';

        return match ([$name, $type]) {
            ['verse', 'tag'] => self::VERSE,
            ['note', 'tag'] => self::NOTE,
            ['char', 'tag'] => self::CHAR,
            ['', 'text'] => self::TEXT,
            default => self::UNKNOWN
        };
    }
}
