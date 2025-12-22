<?php

namespace App\Actions\Chapter;

use App\Enums\BookNameEnum;
use App\Models\Chapter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CompareChaptersAction
{
    public function execute(string $verses, int $number, BookNameEnum $bookName, string $versions): Collection
    {
        $versionIds = $this->parseVersions($versions);
        $verseNumbers = $this->parseVerses($verses);

        return Chapter::whereIn('version_id', $versionIds)
            ->where('number', $number)
            ->whereHas('book', fn(Builder $query) => $query->where('name', $bookName->value))
            ->with([
                'verses' => fn ($query) => $query->when($verseNumbers, fn($q) => $q->whereIn('number', $verseNumbers)),
                'version',
                'book',
            ])
            ->get();
    }

    private function parseVersions(string $versions): array
    {
        return array_map('intval', array_filter(explode(',', $versions)));
    }

    private function parseVerses(string $verses): array
    {
        $numbers = [];
        $parts = explode(',', $verses);

        foreach ($parts as $part) {
            $part = trim($part);
            
            if (str_contains($part, '-')) {
                [$start, $end] = explode('-', $part);

                $numbers = array_merge($numbers, range((int)$start, (int)$end));
            } else {
                $numbers[] = (int)$part;
            }
        }

        return array_unique($numbers);
    }
}
