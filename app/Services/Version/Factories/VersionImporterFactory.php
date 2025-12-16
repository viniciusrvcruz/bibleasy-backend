<?php

namespace App\Services\Version\Factories;

use App\Services\Version\Importers\ThiagoBodrukImporter;
use App\Services\Version\Interfaces\VersionImporterInterface;
use App\Exceptions\Version\VersionImportException;

class VersionImporterFactory
{
    private static array $importers = [
        [
            'name' => 'thiago_bodruk',
            'class' => ThiagoBodrukImporter::class,
        ],
    ];

    public static function getAvailableImporters(): array
    {
        return collect(self::$importers)
            ->map(fn($importer) => $importer['name'])
            ->toArray();
    }

    public static function make(string $importerName): VersionImporterInterface
    {
        $importer = collect(self::$importers)->firstWhere('name', $importerName);

        if(!$importer) {
            throw new VersionImportException('importer_not_found', "Importer type '{$importerName}' not found");
        }

        return new $importer['class']();
    }
}
