<?php

namespace App\Services\Version;

use App\Models\Version;
use App\Services\Version\DTOs\VersionImportDTO;
use App\Services\Version\Factories\VersionParserFactory;
use App\Services\Version\Validators\VersionValidator;
use App\Services\Version\Importers\VersionImporter;
use Illuminate\Support\Facades\DB;

class VersionImportService
{
    public function __construct(
        private readonly VersionValidator $validator,
        private readonly VersionImporter $importer,
    ) {}

    public function import(VersionImportDTO $dto): Version
    {
        $parser = VersionParserFactory::make($dto->importerName);

        $versionData = $parser->parse($dto->content);

        $this->validator->validateBeforeImport($versionData);

        return DB::transaction(function () use ($dto, $versionData) {
            $version = Version::create([
                'name' => $dto->versionName,
                'full_name' => $dto->versionFullName,
                'language' => $dto->language,
                'copyright' => $dto->copyright,
            ]);

            $this->importer->import($versionData, $version->id);

            $version->loadCount(['chapters', 'verses']);

            $this->validator->validateAfterImport($version);

            return $version;
        });
    }
}
