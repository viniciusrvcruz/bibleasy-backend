<?php

namespace App\Services\Chapter\Parsers\ApiBible;

use App\Services\Chapter\DTOs\VerseTitleDTO;

class TitleBuffer
{
    /** @var array<VerseTitleDTO> */
    private array $titles = [];

    public function add(VerseTitleDTO $title): void
    {
        $this->titles[] = $title;
    }

    /**
     * @return array<VerseTitleDTO>
     */
    public function flush(): array
    {
        $titles = $this->titles;
        $this->titles = [];
        return $titles;
    }

    public function isEmpty(): bool
    {
        return empty($this->titles);
    }
}
