<?php

namespace App\Services\Version\Factories;

use App\Enums\VersionTextSourceEnum;
use App\Services\Version\DTOs\FileDTO;
use App\Services\Version\DTOs\VersionImportDTO;
use Illuminate\Http\Request;

class VersionImportDTOFactory
{
    /**
     * Create VersionImportDTO from HTTP Request
     */
    public static function fromRequest(Request $request): VersionImportDTO
    {
        $files = collect($request->file('files'))->map(fn ($file) => new FileDTO(
            content: $file->getContent(),
            fileName: $file->getClientOriginalName(),
            extension: $file->getClientOriginalExtension(),
        ))->toArray();

        $textSource = VersionTextSourceEnum::from($request->input('text_source'));

        return new VersionImportDTO(
            files: $files,
            adapterName: $request->input('adapter'),
            versionAbbreviation: $request->input('abbreviation'),
            versionName: $request->input('name'),
            language: $request->input('language'),
            copyright: $request->input('copyright', ''),
            textSource: $textSource,
            externalVersionId: $request->input('external_version_id'),
            cacheTtl: $textSource !== VersionTextSourceEnum::DATABASE
                ? (int) $request->input('cache_ttl')
                : null,
        );
    }
}

