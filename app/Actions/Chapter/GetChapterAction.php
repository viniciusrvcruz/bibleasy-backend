<?php

namespace App\Actions\Chapter;

use App\Enums\BookAbbreviationEnum;
use App\Models\Version;
use App\Services\Chapter\DTOs\ChapterResponseDTO;
use App\Services\Chapter\Factories\ChapterSourceAdapterFactory;
use Illuminate\Support\Facades\Cache;

class GetChapterAction
{
    public function execute(
        int $number,
        BookAbbreviationEnum $abbreviation,
        Version $version
    ): ChapterResponseDTO {
        $cacheKey = "bible:{$version->abbreviation}:{$abbreviation->value}:{$number}";

        $ttl = $version->cache_ttl
            ? now()->addSeconds($version->cache_ttl)
            : null;

        return Cache::remember($cacheKey, $ttl, function () use ($number, $abbreviation, $version) {
            $adapter = ChapterSourceAdapterFactory::make($version);

            return $adapter->getChapter($version, $abbreviation, $number);
        });
    }
}
