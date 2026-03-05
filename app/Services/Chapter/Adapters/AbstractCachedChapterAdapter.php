<?php

namespace App\Services\Chapter\Adapters;

use App\Enums\BookAbbreviationEnum;
use App\Models\Version;
use App\Services\Chapter\DTOs\ChapterResponseDTO;
use App\Services\Chapter\Interfaces\ChapterSourceAdapterInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Base adapter that caches raw source data (API response or DB result) and applies
 * processRawToDto on every request. This way, code changes (e.g. parser or DTO mapping)
 * take effect immediately without cache invalidation; only the raw data is cached.
 */
abstract class AbstractCachedChapterAdapter implements ChapterSourceAdapterInterface
{
    public function getChapter(
        Version $version,
        BookAbbreviationEnum $abbreviation,
        int $number
    ): ChapterResponseDTO {
        $key = $this->buildCacheKey($version, $abbreviation, $number);
        $ttl = $version->cache_ttl
            ? now()->addSeconds($version->cache_ttl)
            : null;

        $raw = $ttl !== null
            ? Cache::remember($key, $ttl, fn () => $this->fetchRawChapter($version, $abbreviation, $number))
            : Cache::rememberForever($key, fn () => $this->fetchRawChapter($version, $abbreviation, $number));

        return $this->processRawToDto($raw, $version, $abbreviation, $number);
    }

    protected function buildCacheKey(Version $version, BookAbbreviationEnum $abbreviation, int $number): string
    {
        return "versions:{$version->id}:books:{$abbreviation->value}:chapters:{$number}";
    }

    /**
     * Fetch raw chapter data from source. Return value must be serializable (array).
     */
    abstract protected function fetchRawChapter(
        Version $version,
        BookAbbreviationEnum $abbreviation,
        int $number
    ): array;

    /**
     * Transform raw data into ChapterResponseDTO. Runs on every request so code changes apply without cache invalidation.
     */
    abstract protected function processRawToDto(
        array $raw,
        Version $version,
        BookAbbreviationEnum $abbreviation,
        int $number
    ): ChapterResponseDTO;
}
