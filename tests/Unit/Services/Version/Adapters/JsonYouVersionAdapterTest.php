<?php

use App\Services\Version\Adapters\JsonYouVersionAdapter;
use App\Services\Version\DTOs\FileDTO;
use App\Services\Version\DTOs\VersionDTO;
use App\Exceptions\Version\VersionImportException;

describe('JsonYouVersionAdapter', function () {
    beforeEach(function () {
        $this->adapter = new JsonYouVersionAdapter();
    });

    it('adapts valid youversion json to DTO', function () {
        $json = json_encode([
            'text_direction' => 'ltr',
            'books' => [
                [
                    'id' => 'GEN',
                    'title' => 'Gênesis',
                    'chapters' => [
                        [
                            'id' => '1',
                            'verses' => [
                                ['id' => '1'],
                                ['id' => '2'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $files = [
            new FileDTO(
                content: $json,
                fileName: 'youversion.json',
                extension: 'json'
            ),
        ];

        $result = $this->adapter->adapt($files);

        expect($result)->toBeInstanceOf(VersionDTO::class)
            ->and($result->books)->toHaveCount(1)
            ->and($result->books[0]->name)->toBe('Gênesis')
            ->and($result->books[0]->abbreviation->value)->toBe('gen')
            ->and($result->books[0]->chapters)->toHaveCount(1)
            ->and($result->books[0]->chapters[0]->number)->toBe(1)
            ->and($result->books[0]->chapters[0]->verses)->toHaveCount(2)
            ->and($result->books[0]->chapters[0]->verses[0]->number)->toBe(1)
            ->and($result->books[0]->chapters[0]->verses[0]->text)->toBe('')
            ->and($result->books[0]->chapters[0]->verses[1]->number)->toBe(2);
    });

    it('removes BOM from content', function () {
        $bom = pack('H*', 'EFBBBF');
        $json = $bom . json_encode([
            'books' => [
                [
                    'id' => 'EXO',
                    'title' => 'Êxodo',
                    'chapters' => [
                        ['id' => '1', 'verses' => [['id' => '1']]],
                    ],
                ],
            ],
        ]);

        $files = [
            new FileDTO(content: $json, fileName: 'bible.json', extension: 'json'),
        ];

        $result = $this->adapter->adapt($files);

        expect($result)->toBeInstanceOf(VersionDTO::class)
            ->and($result->books[0]->abbreviation->value)->toBe('exo');
    });

    it('throws exception for empty files array', function () {
        $this->adapter->adapt([]);
    })->throws(VersionImportException::class, 'At least one file is required');

    it('throws exception for non-json file extension', function () {
        $json = json_encode(['books' => []]);
        $files = [
            new FileDTO(content: $json, fileName: 'bible.txt', extension: 'txt'),
        ];

        $this->adapter->adapt($files);
    })->throws(VersionImportException::class, 'File must have .json extension');

    it('throws exception when books key is missing', function () {
        $json = json_encode(['text_direction' => 'ltr']);
        $files = [
            new FileDTO(content: $json, fileName: 'bible.json', extension: 'json'),
        ];

        $this->adapter->adapt($files);
    })->throws(VersionImportException::class, "JSON must contain a 'books' array");

    it('throws exception when book has no id', function () {
        $json = json_encode([
            'books' => [
                [
                    'title' => 'Gênesis',
                    'chapters' => [['id' => '1', 'verses' => [['id' => '1']]]],
                ],
            ],
        ]);
        $files = [
            new FileDTO(content: $json, fileName: 'bible.json', extension: 'json'),
        ];

        $this->adapter->adapt($files);
    })->throws(VersionImportException::class, "must have an 'id' attribute");

    it('throws exception when book has no title', function () {
        $json = json_encode([
            'books' => [
                [
                    'id' => 'GEN',
                    'chapters' => [['id' => '1', 'verses' => [['id' => '1']]]],
                ],
            ],
        ]);
        $files = [
            new FileDTO(content: $json, fileName: 'bible.json', extension: 'json'),
        ];

        $this->adapter->adapt($files);
    })->throws(VersionImportException::class, "must have a 'title' attribute");

    it('throws exception for invalid book id', function () {
        $json = json_encode([
            'books' => [
                [
                    'id' => 'INVALID_BOOK',
                    'title' => 'Invalid',
                    'chapters' => [['id' => '1', 'verses' => [['id' => '1']]]],
                ],
            ],
        ]);
        $files = [
            new FileDTO(content: $json, fileName: 'bible.json', extension: 'json'),
        ];

        $this->adapter->adapt($files);
    })->throws(VersionImportException::class, 'does not match any known book abbreviation');

    it('throws exception when chapter has no id', function () {
        $json = json_encode([
            'books' => [
                [
                    'id' => 'GEN',
                    'title' => 'Gênesis',
                    'chapters' => [
                        ['verses' => [['id' => '1']]],
                    ],
                ],
            ],
        ]);
        $files = [
            new FileDTO(content: $json, fileName: 'bible.json', extension: 'json'),
        ];

        $this->adapter->adapt($files);
    })->throws(VersionImportException::class, "must have an 'id' attribute");

    it('throws exception when verse has no id', function () {
        $json = json_encode([
            'books' => [
                [
                    'id' => 'GEN',
                    'title' => 'Gênesis',
                    'chapters' => [
                        ['id' => '1', 'verses' => [['title' => '1']]],
                    ],
                ],
            ],
        ]);
        $files = [
            new FileDTO(content: $json, fileName: 'bible.json', extension: 'json'),
        ];

        $this->adapter->adapt($files);
    })->throws(VersionImportException::class, "must have an 'id' attribute");

    it('throws exception for invalid json', function () {
        $files = [
            new FileDTO(
                content: 'not json at all',
                fileName: 'bible.json',
                extension: 'json'
            ),
        ];

        $this->adapter->adapt($files);
    })->throws(VersionImportException::class, 'Invalid JSON format');
});
