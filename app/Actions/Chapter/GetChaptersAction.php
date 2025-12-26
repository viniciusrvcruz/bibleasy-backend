<?php

namespace App\Actions\Chapter;

use App\Enums\BookNameEnum;
use App\Models\Book;
use Illuminate\Database\Eloquent\Collection;

class GetChaptersAction
{
    public function execute(BookNameEnum $bookName, int $versionId): Collection
    {
        $book = Book::where('name', $bookName->value)->firstOrFail();

        return $book->chapters()
            ->where('version_id', $versionId)
            ->withCount('verses')
            ->orderBy('number')
            ->get();
    }
}
