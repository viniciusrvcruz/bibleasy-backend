<?php

namespace App\Services\Version;

use App\Models\Version;
use App\Services\Version\DTOs\VersionImportDTO;
use App\Services\Version\Factories\VersionImporterFactory;
use Illuminate\Support\Facades\DB;
use App\Exceptions\Version\VersionImportException;

class VersionImportService
{
    public function import(VersionImportDTO $dto): Version
    {
        $importer = VersionImporterFactory::make($dto->importerName);

        $data = $importer->parse($dto->content);

        $importer->validate($data);

        return DB::transaction(function () use ($dto, $data, $importer) {
            $version = Version::create([
                'name' => $dto->versionName,
                'language' => $dto->language,
                'copyright' => $dto->copyright,
            ]);

            $importer->import($data, $version->id);

            $version->loadCount(['chapters', 'verses']);

            $this->validateImport($version);

            return $version;
        });
    }

    private function validateImport(Version $version): void
    {
        $chaptersCount = $version->chapters_count;
        $versesCount = $version->verses_count;

        if ($chaptersCount !== 1189) {
            throw new VersionImportException('invalid_chapters_count', "Expected 1,189 chapters but got {$chaptersCount}");
        }

        if ($versesCount < 31100 || $versesCount > 31110) {
            throw new VersionImportException('invalid_verses_count', "Expected verses between 31,100 and 31,110 but got {$versesCount}");
        }
    }
}
