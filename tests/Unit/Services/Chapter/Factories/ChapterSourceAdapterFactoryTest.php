<?php

uses(Tests\TestCase::class);

use App\Enums\VersionTextSourceEnum;
use App\Models\Version;
use App\Services\Chapter\Adapters\ApiBibleChapterAdapter;
use App\Services\Chapter\Adapters\DatabaseChapterAdapter;
use App\Services\Chapter\Interfaces\ChapterSourceAdapterInterface;
use App\Services\Chapter\Factories\ChapterSourceAdapterFactory;

describe('ChapterSourceAdapterFactory', function () {
    it('returns DatabaseChapterAdapter when text_source is DATABASE', function () {
        $version = Version::factory()->create(['text_source' => VersionTextSourceEnum::DATABASE]);

        $adapter = ChapterSourceAdapterFactory::make($version);

        expect($adapter)->toBeInstanceOf(ChapterSourceAdapterInterface::class)
            ->and($adapter)->toBeInstanceOf(DatabaseChapterAdapter::class);
    });

    it('returns ApiBibleChapterAdapter when text_source is API_BIBLE', function () {
        $version = Version::factory()->create(['text_source' => VersionTextSourceEnum::API_BIBLE]);

        $adapter = ChapterSourceAdapterFactory::make($version);

        expect($adapter)->toBeInstanceOf(ChapterSourceAdapterInterface::class)
            ->and($adapter)->toBeInstanceOf(ApiBibleChapterAdapter::class);
    });
});
