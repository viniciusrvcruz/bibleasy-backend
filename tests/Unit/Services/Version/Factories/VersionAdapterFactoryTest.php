<?php

use App\Services\Version\Factories\VersionAdapterFactory;
use App\Services\Version\Adapters\JsonThiagoBodrukAdapter;
use App\Exceptions\Version\VersionImportException;
use App\Services\Version\Interfaces\VersionAdapterInterface;

describe('VersionAdapterFactory', function () {
    it('resolves json thiago_bodruk adapter', function () {
        $adapter = VersionAdapterFactory::make('json_thiago_bodruk');

        expect($adapter)->toBeInstanceOf(VersionAdapterInterface::class)
            ->and($adapter)->toBeInstanceOf(JsonThiagoBodrukAdapter::class);
    });

    it('throws exception for unknown format', function () {
        VersionAdapterFactory::make('unknown');
    })->throws(VersionImportException::class, "Adapter for name 'unknown' not found");

    it('returns available adapter names', function () {
        $adapterNames = VersionAdapterFactory::getAvailableAdapterNames();

        expect($adapterNames)->toBeArray()
            ->and($adapterNames)->toContain('json_thiago_bodruk');
    });
});

