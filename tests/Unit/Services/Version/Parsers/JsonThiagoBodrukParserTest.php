<?php

use App\Services\Version\Parsers\JsonThiagoBodrukParser;
use App\Services\Version\DTOs\VersionDTO;
use App\Exceptions\Version\VersionImportException;

describe('JsonThiagoBodrukParser', function () {
    beforeEach(function () {
        $this->parser = new JsonThiagoBodrukParser();
    });

    it('parses valid json to DTO', function () {
        $json = json_encode([
            ['chapters' => [['Verse 1', 'Verse 2']]],
        ]);

        $result = $this->parser->parse($json);

        expect($result)->toBeInstanceOf(VersionDTO::class)
            ->and($result->books)->toHaveCount(1)
            ->and($result->books[0]->chapters)->toHaveCount(1)
            ->and($result->books[0]->chapters[0]->verses)->toHaveCount(2)
            ->and($result->books[0]->chapters[0]->verses[0]->number)->toBe(1)
            ->and($result->books[0]->chapters[0]->verses[0]->text)->toBe('Verse 1');
    });

    it('removes BOM from content', function () {
        $bom = pack('H*','EFBBBF');
        $json = $bom . json_encode([['chapters' => [['Verse']]]]);

        $result = $this->parser->parse($json);

        expect($result)->toBeInstanceOf(VersionDTO::class);
    });

    it('throws exception for invalid json', function () {
        $this->parser->parse('{"invalid": "structure"}');
    })->throws(VersionImportException::class, 'Book invalid must be an array of chapters');

    it('throws exception for malformed json', function () {
        $this->parser->parse('not json at all');
    })->throws(VersionImportException::class);

    it('throws exception for book without chapters array', function () {
        $json = json_encode([['invalid' => 'structure']]);
        $this->parser->parse($json);
    })->throws(VersionImportException::class, 'Book 0 must be an array of chapters');

    it('throws exception for non-array chapter', function () {
        $json = json_encode([['chapters' => ['not an array']]]);
        $this->parser->parse($json);
    })->throws(VersionImportException::class, 'must be an array');

    it('throws exception for non-string verse', function () {
        $json = json_encode([['chapters' => [[123]]]]);
        $this->parser->parse($json);
    })->throws(VersionImportException::class, 'must be a string');
});
