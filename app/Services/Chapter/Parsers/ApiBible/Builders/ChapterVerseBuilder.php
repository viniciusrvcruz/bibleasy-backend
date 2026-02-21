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
     * @return Collection<int, VerseResponseDTO>
     */
    public function build(): Collection
    {
        ksort($this->verses);

        return collect($this->verses)
            ->map(fn(VerseBuilder $data, int $number) => new VerseResponseDTO(
                number: $number,
                text: trim($data->getFullText()),
                titles: collect($data->titles),
                references: collect($data->references)
            ))
            ->values();
    }
}
