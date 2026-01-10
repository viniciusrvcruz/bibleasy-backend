<?php

namespace App\Services\Version;

use App\Models\Version;
use App\Services\Version\DTOs\VersionImportDTO;
use App\Services\Version\Factories\VersionAdapterFactory;
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
        $adapter = VersionAdapterFactory::make($dto->adapterName);

        $versionData = $adapter->adapt($dto->files);

        $this->validator->validate($versionData);

        return DB::transaction(function () use ($dto, $versionData) {
            $version = Version::create([
                'abbreviation' => $dto->versionAbbreviation,
                'name' => $dto->versionName,
                'language' => $dto->language,
                'copyright' => $dto->copyright,
            ]);

            $this->importer->import($versionData, $version->id);

            return $version;
        });
    }
}
