<?php

namespace App\Actions\Chapter;

use App\Enums\BookAbbreviationEnum;
use App\Models\Chapter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CompareChaptersAction
{
    public function execute(string $verses, int $number, BookAbbreviationEnum $abbreviation, string $versions): Collection
    {
        $versionIds = $this->parseVersions($versions);
        $verseNumbers = $this->parseVerses($verses);

        return Chapter::where('number', $number)
            ->whereHas('book', fn(Builder $query) => $query
                ->where('abbreviation', $abbreviation)
                ->whereIn('version_id', $versionIds))
            ->with([
                'verses' => fn ($query) => $query->when($verseNumbers, fn($q) => $q->whereIn('number', $verseNumbers)),
                'book.version',
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
