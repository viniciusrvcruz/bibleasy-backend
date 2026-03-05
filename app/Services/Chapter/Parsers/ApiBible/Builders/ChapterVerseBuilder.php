<?php

namespace App\Services\Chapter\Parsers\ApiBible\Builders;

use App\Services\Chapter\DTOs\VerseResponseDTO;
use Illuminate\Support\Collection;

class ChapterVerseBuilder
{
    /** @var array<int, VerseBuilder> */
    private array $verses = [];

    public function getOrCreate(int $verseNumber): VerseBuilder
    {
        if (!isset($this->verses[$verseNumber])) {
            $this->verses[$verseNumber] = new VerseBuilder();
        }

        return $this->verses[$verseNumber];
    }

    public function exists(int $verseNumber): bool
    {
        return isset($this->verses[$verseNumber]);
    }

    /**
     * Returns the last (highest) verse number that has content, or null if none.
     */
    public function getLastVerseNumberWithContent(): ?int
    {
        $numbers = array_keys($this->verses);
        if ($numbers === []) {
            return null;
        }

        $max = max($numbers);
        $verse = $this->verses[$max];

        return $verse->hasContent() ? $max : null;
    }

    /**
     * @return Collection<int, VerseResponseDTO>
     */
    public function build(): Collection
    {
        ksort($this->verses);

        return collect($this->verses)
            ->map(fn(VerseBuilder $data, int $number) => new VerseResponseDTO(
                number: $number,
                text: rtrim($data->getFullText(), " \t"),
                titles: collect($data->titles),
                references: collect($data->references)
            ))
            ->values();
    }
}
