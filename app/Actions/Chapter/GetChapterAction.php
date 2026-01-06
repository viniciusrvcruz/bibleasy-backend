<?php

namespace App\Actions\Chapter;

use App\Enums\BookAbbreviationEnum;
use App\Models\Chapter;
use Illuminate\Database\Eloquent\Builder;

class GetChapterAction
{
    public function execute(int $number, BookAbbreviationEnum $bookName, int $versionId): Chapter
    {
        $chapter = Chapter::where('number', $number)
            ->whereHas('book', fn(Builder $query) => $query
                ->where('abbreviation', $bookName)
                ->where('version_id', $versionId))
            ->with(['verses', 'book'])
            ->firstOrFail();

        $chapter->setRelation('previous', $this->getAdjacentChapter($versionId, $chapter->position - 1));
        $chapter->setRelation('next', $this->getAdjacentChapter($versionId, $chapter->position + 1));

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
