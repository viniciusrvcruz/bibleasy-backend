<?php

namespace App\Utils;

/**
 * JSON decode utility with UTF-8 BOM stripping.
 */
class JsonDecode
{
    /**
     * Decode JSON string to array, stripping UTF-8 BOM if present.
     * Returns null when content is not valid JSON.
     */
    public static function toArray(string $content): ?array
    {
        $bom = pack('H*', 'EFBBBF');
        $content = preg_replace("/^$bom/", '', $content);

        $decoded = json_decode($content, true, 512);

        return is_array($decoded) ? $decoded : null;
    }
}
