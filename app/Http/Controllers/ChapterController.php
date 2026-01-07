<?php

namespace App\Http\Controllers;

use App\Actions\Chapter\CompareChaptersAction;
use App\Actions\Chapter\GetChapterAction;
use App\Enums\BookAbbreviationEnum;
use App\Http\Resources\ChapterResource;
use App\Models\Version;
use Illuminate\Http\Request;

class ChapterController extends Controller
{
    public function show(Version $version, BookAbbreviationEnum $abbreviation, int $number)
    {
        $chapter = app(GetChapterAction::class)->execute(
            number: $number,
            abbreviation: $abbreviation,
            version: $version
        );

        return new ChapterResource($chapter);
    }

    public function comparison(Request $request, BookAbbreviationEnum $abbreviation, int $number)
    {
        $verses = $request->string('verses');
        $versions = $request->string('versions');

        $chapters = app(CompareChaptersAction::class)->execute(
            verses: $verses,
            number: $number,
            abbreviation: $abbreviation,
            versions: $versions
        );

        return ChapterResource::collection($chapters);
    }
}
