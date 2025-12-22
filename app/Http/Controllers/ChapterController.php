<?php

namespace App\Http\Controllers;

use App\Actions\Chapter\CompareChaptersAction;
use App\Actions\Chapter\GetChapterAction;
use App\Enums\BookNameEnum;
use App\Http\Resources\ChapterResource;
use Illuminate\Http\Request;

class ChapterController extends Controller
{
    public function show(Request $request, BookNameEnum $book, int $number)
    {
        $versionId = $request->integer('version_id');

        $chapter = app(GetChapterAction::class)->execute($number, $book, $versionId);

        return new ChapterResource($chapter);
    }

    public function compare(Request $request, BookNameEnum $book, int $number)
    {
        $verses = $request->string('verses');
        $versions = $request->string('versions');

        $chapters = app(CompareChaptersAction::class)->execute($verses, $number, $book, $versions);

        return ChapterResource::collection($chapters);
    }
}
