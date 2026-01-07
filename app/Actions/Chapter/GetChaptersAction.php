<?php

namespace App\Actions\Chapter;

use App\Enums\BookAbbreviationEnum;
use App\Models\Book;
use App\Models\Version;
use Illuminate\Database\Eloquent\Collection;

class GetChaptersAction
{
    public function execute(BookAbbreviationEnum $abbreviation, Version $version): Collection
    {
        $book = Book::where('abbreviation', $abbreviation)
            ->where('version_id', $version->id)
            ->firstOrFail();

        return $book->chapters()
            ->orderBy('number')
            ->get();
    }
}
