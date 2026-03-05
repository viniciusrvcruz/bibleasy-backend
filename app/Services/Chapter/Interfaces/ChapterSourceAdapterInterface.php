<?php

namespace App\Services\Chapter\Interfaces;

use App\Enums\BookAbbreviationEnum;
use App\Models\Version;
use App\Services\Chapter\DTOs\ChapterResponseDTO;

interface ChapterSourceAdapterInterface
{
    public function getChapter(
        Version $version,
        BookAbbreviationEnum $abbreviation,
        int $number
    ): ChapterResponseDTO;
}
