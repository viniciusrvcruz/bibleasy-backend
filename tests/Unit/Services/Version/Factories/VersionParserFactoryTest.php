<?php

use App\Services\Version\Factories\VersionParserFactory;
use App\Services\Version\Parsers\JsonThiagoBodrukParser;
use App\Exceptions\Version\VersionImportException;
use App\Services\Version\Interfaces\VersionParserInterface;

describe('VersionParserFactory', function () {
    it('resolves json thiago_bodruk parser', function () {
        $parser = VersionParserFactory::make('json_thiago_bodruk');

        expect($parser)->toBeInstanceOf(VersionParserInterface::class)
            ->and($parser)->toBeInstanceOf(JsonThiagoBodrukParser::class);
    });

    it('throws exception for unknown format', function () {
        VersionParserFactory::make('unknown');
    })->throws(VersionImportException::class, "Parser for format 'unknown' not found");

    it('returns available formats', function () {
        $formats = VersionParserFactory::getAvailableFormats();

        expect($formats)->toBeArray()
            ->and($formats)->toContain('json_thiago_bodruk');
    });
});
