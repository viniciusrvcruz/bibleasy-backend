<?php

use App\Services\Version\Parsers\JsonThiagoBodrukParser;
use App\Services\Version\DTOs\FileDTO;
use App\Services\Version\DTOs\VersionDTO;
use App\Exceptions\Version\VersionImportException;

describe('JsonThiagoBodrukParser', function () {
    beforeEach(function () {
        $this->parser = new JsonThiagoBodrukParser();
    });

    it('parses valid json to DTO', function () {
        $json = json_encode([
            ['name' => 'Gênesis', 'chapters' => [['Verse 1', 'Verse 2']]],
        ]);

        $files = [
            new FileDTO(
                content: $json,
                fileName: 'bible.json',
                extension: 'json'
            )
        ];

        $result = $this->parser->parse($files);

        expect($result)->toBeInstanceOf(VersionDTO::class)
            ->and($result->books)->toHaveCount(1)
            ->and($result->books[0]->chapters)->toHaveCount(1)
            ->and($result->books[0]->chapters[0]->verses)->toHaveCount(2)
            ->and($result->books[0]->chapters[0]->verses[0]->number)->toBe(1)
            ->and($result->books[0]->chapters[0]->verses[0]->text)->toBe('Verse 1')
            ->and($result->books[0]->name)->toBe('Gênesis')
            ->and($result->books[0]->abbreviation->value)->toBe('gen');
    });

    it('removes BOM from content', function () {
        $bom = pack('H*','EFBBBF');
        $json = $bom . json_encode([['name' => 'Gênesis', 'chapters' => [['Verse']]]]);

        $files = [
            new FileDTO(
                content: $json,
                fileName: 'bible.json',
                extension: 'json'
            )
        ];

        $result = $this->parser->parse($files);

        expect($result)->toBeInstanceOf(VersionDTO::class);
    });

    it('throws exception for empty files array', function () {
        $this->parser->parse([]);
    })->throws(VersionImportException::class, 'At least one file is required');

    it('throws exception for non-json file extension', function () {
        $json = json_encode([['name' => 'Gênesis', 'chapters' => [['Verse']]]]);

        $files = [
            new FileDTO(
                content: $json,
                fileName: 'bible.txt',
                extension: 'txt'
            )
        ];

        $this->parser->parse($files);
    })->throws(VersionImportException::class, 'File must have .json extension');

    it('throws exception for missing book name', function () {
        $json = json_encode([['chapters' => [['Verse']]]]);

        $files = [
            new FileDTO(
                content: $json,
                fileName: 'bible.json',
                extension: 'json'
            )
        ];

        $this->parser->parse($files);
    })->throws(VersionImportException::class, "Book 0 must have a 'name' attribute");

    it('throws exception for invalid json', function () {
        $files = [
            new FileDTO(
                content: '{"invalid": "structure"}',
                fileName: 'bible.json',
                extension: 'json'
            )
        ];

        $this->parser->parse($files);
    })->throws(VersionImportException::class);

    it('throws exception for malformed json', function () {
        $files = [
            new FileDTO(
                content: 'not json at all',
                fileName: 'bible.json',
                extension: 'json'
            )
        ];

        $this->parser->parse($files);
    })->throws(VersionImportException::class);

    it('throws exception for book without chapters array', function () {
        $json = json_encode([['name' => 'Gênesis', 'invalid' => 'structure']]);

        $files = [
            new FileDTO(
                content: $json,
                fileName: 'bible.json',
                extension: 'json'
            )
        ];

        $this->parser->parse($files);
    })->throws(VersionImportException::class, 'must be an array of chapters');

    it('throws exception for non-array chapter', function () {
        $json = json_encode([['name' => 'Gênesis', 'chapters' => ['not an array']]]);

        $files = [
            new FileDTO(
                content: $json,
                fileName: 'bible.json',
                extension: 'json'
            )
        ];

        $this->parser->parse($files);
    })->throws(VersionImportException::class, 'must be an array');

    it('throws exception for non-string verse', function () {
        $json = json_encode([['name' => 'Gênesis', 'chapters' => [[123]]]]);

        $files = [
            new FileDTO(
                content: $json,
                fileName: 'bible.json',
                extension: 'json'
            )
        ];

        $this->parser->parse($files);
    })->throws(VersionImportException::class, 'must be a string');
});
