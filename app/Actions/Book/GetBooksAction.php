<?php

namespace App\Actions\Book;

use App\Models\Version;
use Illuminate\Database\Eloquent\Collection;

class GetBooksAction
{
    /**
     * Get all books for a specific version with their chapters.
     */
    public function execute(Version $version): Collection
    {
        return $version->books()
            ->with([
                'chapters' => function ($query) {
                    $query->withCount('verses')
                        ->orderBy('number');
                },
            ])
            ->orderBy('order')
            ->get();
    }
}
