<?php

namespace App\Actions\Chapter;

use App\Enums\BookAbbreviationEnum;
use App\Models\Chapter;
use App\Models\Version;
use Illuminate\Database\Eloquent\Builder;

class GetChapterAction
{
    public function execute(
        int $number,
        BookAbbreviationEnum $abbreviation,
        Version $version
    ): Chapter
    {
        return Chapter::where('number', $number)
            ->whereHas('book', fn(Builder $query) => $query
                ->where('abbreviation', $abbreviation)
                ->where('version_id', $version->id))
            ->with(['verses', 'book'])
            ->firstOrFail();
    }
}
