<?php

namespace App\Actions\Chapter;

use App\Enums\BookAbbreviationEnum;
use App\Models\Book;
use Illuminate\Database\Eloquent\Collection;

class GetChaptersAction
{
    public function execute(BookAbbreviationEnum $abbreviation, int $versionId): Collection
    {
        $book = Book::where('abbreviation', $abbreviation)
            ->where('version_id', $versionId)
            ->firstOrFail();

        return $book->chapters()
            ->orderBy('number')
            ->get();
    }
}
