<?php

namespace App\Services\Chapter\Factories;

use App\Enums\VersionTextSourceEnum;
use App\Models\Version;
use App\Services\Chapter\Adapters\ApiBibleChapterAdapter;
use App\Services\Chapter\Adapters\DatabaseChapterAdapter;
use App\Services\Chapter\Interfaces\ChapterSourceAdapterInterface;

class ChapterSourceAdapterFactory
{
    public static function make(Version $version): ChapterSourceAdapterInterface
    {
        $source = $version->text_source ?? VersionTextSourceEnum::DATABASE;

        return match ($source) {
            VersionTextSourceEnum::DATABASE => app(DatabaseChapterAdapter::class),
            VersionTextSourceEnum::API_BIBLE => app(ApiBibleChapterAdapter::class),
        };
    }
}
