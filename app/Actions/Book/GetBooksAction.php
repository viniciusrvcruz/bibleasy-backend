<?php

namespace App\Actions\Book;

use App\Models\Version;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class GetBooksAction
{
    /**
     * Get all books for a specific version with their chapters.
     */
    public function execute(Version $version): Collection
    {
        $cacheKey = "versions:{$version->id}:books";

        $ttl = $version->cache_ttl
            ? now()->addSeconds($version->cache_ttl)
            : null;

        return Cache::remember($cacheKey, $ttl, function () use ($version) {
            return $version->books()
                ->with([
                    'chapters' => function ($query) {
                        $query->withCount('verses')
                            ->orderBy('number');
                    },
                ])
                ->orderBy('order')
                ->get();
        });
    }
}
