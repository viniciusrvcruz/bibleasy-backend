<?php

namespace App\Actions\Chapter;

use App\Enums\BookAbbreviationEnum;
use App\Models\Chapter;
use App\Models\Version;
use Illuminate\Database\Eloquent\Builder;

class GetChapterAction
{
    public function execute(int $number, BookAbbreviationEnum $abbreviation, Version $version): Chapter
    {
        $chapter = Chapter::where('number', $number)
            ->whereHas('book', fn(Builder $query) => $query
                ->where('abbreviation', $abbreviation)
                ->where('version_id', $version->id))
            ->with(['verses', 'book'])
            ->firstOrFail();

        $chapter->setRelation('previous', $this->getAdjacentChapter($version->id, $chapter->position - 1));
        $chapter->setRelation('next', $this->getAdjacentChapter($version->id, $chapter->position + 1));

        return $chapter;
    }

    private function getAdjacentChapter(int $versionId, int $position): ?Chapter
    {
        return Chapter::whereHas('book', fn(Builder $query) => $query->where('version_id', $versionId))
            ->where('position', $position)
            ->with(['verses', 'book'])
            ->first();
    }
}
