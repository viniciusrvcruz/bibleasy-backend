<?php

namespace App\Http\Controllers;

use App\Actions\Chapter\CompareChaptersAction;
use App\Actions\Chapter\GetChapterAction;
use App\Actions\Chapter\GetChaptersAction;
use App\Enums\BookAbbreviationEnum;
use App\Http\Resources\ChapterResource;
use Illuminate\Http\Request;

class ChapterController extends Controller
{
    public function index(Request $request, BookAbbreviationEnum $abbreviation)
    {
        $versionId = $request->integer('version_id');

        $chapters = app(GetChaptersAction::class)->execute(
            abbreviation: $abbreviation,
            versionId: $versionId
        );

        return ChapterResource::collection($chapters);
    }

    public function show(Request $request, BookAbbreviationEnum $abbreviation, int $number)
    {
        $versionId = $request->integer('version_id');

        $chapter = app(GetChapterAction::class)->execute(
            number: $number,
            abbreviation: $abbreviation,
            versionId: $versionId
        );

        return new ChapterResource($chapter);
    }

    public function compare(Request $request, BookAbbreviationEnum $abbreviation, int $number)
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
