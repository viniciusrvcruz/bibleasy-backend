<?php

namespace App\Services\Version\Factories;

use App\Services\Version\Interfaces\VersionAdapterInterface;
use App\Services\Version\Adapters\JsonThiagoBodrukAdapter;
use App\Services\Version\Adapters\UsfmAdapter;
use App\Exceptions\Version\VersionImportException;

class VersionAdapterFactory
{
    private static array $adapters = [
        'json_thiago_bodruk' => JsonThiagoBodrukAdapter::class,
        'usfm' => UsfmAdapter::class,
    ];

    public static function make(string $name): VersionAdapterInterface
    {
        $adapterClass = self::$adapters[$name] ?? null;

        if (!$adapterClass) {
            throw new VersionImportException('adapter_not_found', "Adapter for name '{$name}' not found");
        }

        return app($adapterClass);
    }

    public static function getAvailableAdapterNames(): array
    {
        return array_keys(self::$adapters);
    }
}

