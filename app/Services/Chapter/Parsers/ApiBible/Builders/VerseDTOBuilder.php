<?php

namespace App\Services\Chapter\Parsers\ApiBible\Builders;

use App\Services\Chapter\DTOs\VerseResponseDTO;
use App\Services\Chapter\Parsers\ApiBible\ValueObjects\VerseData;
use Illuminate\Support\Collection;

class VerseDTOBuilder
{
    /** @var array<int, VerseData> */
    private array $verses = [];

    public function getOrCreate(int $verseNumber): VerseData
    {
        if (!isset($this->verses[$verseNumber])) {
            $this->verses[$verseNumber] = new VerseData();
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
            ->map(fn(VerseData $data, int $number) => new VerseResponseDTO(
                number: $number,
                text: trim($data->getFullText()),
                titles: collect($data->titles),
                references: collect($data->references)
            ))
            ->values();
    }
}
