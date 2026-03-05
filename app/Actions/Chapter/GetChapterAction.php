<?php

namespace App\Actions\Chapter;

use App\Enums\BookAbbreviationEnum;
use App\Models\Version;
use App\Services\Chapter\DTOs\ChapterResponseDTO;
use App\Services\Chapter\Factories\ChapterSourceAdapterFactory;

class GetChapterAction
{
    public function execute(
        int $number,
        BookAbbreviationEnum $abbreviation,
        Version $version
    ): ChapterResponseDTO {
        $adapter = ChapterSourceAdapterFactory::make($version);

        return $adapter->getChapter($version, $abbreviation, $number);
    }
}
