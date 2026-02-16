<?php

use App\Enums\VerseTitleTypeEnum;
use App\Services\Chapter\Parsers\ApiBibleContentParser;

describe('ApiBibleContentParser', function () {
    it('extracts verses with section title and reference from cl', function () {
        $content = [
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'cl'],
                'items' => [
                    ['text' => 'Salmo 119 ', 'type' => 'text'],
                    [
                        'name' => 'note',
                        'type' => 'tag',
                        'attrs' => ['style' => 'f', 'verseId' => 'PSA.119.1'],
                        'items' => [
                            [
                                'name' => 'char',
                                'type' => 'tag',
                                'attrs' => ['style' => 'ft'],
                                'items' => [['text' => 'O salmo 119 é um poema organizado em ordem alfabética, no hebraico.', 'type' => 'text']],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'd'],
                'items' => [['text' => 'Álef', 'type' => 'text']],
            ],
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'q1'],
                'items' => [
                    ['name' => 'verse', 'type' => 'tag', 'attrs' => ['number' => '1', 'style' => 'v'], 'items' => [['text' => '1', 'type' => 'text']]],
                    ['text' => 'Como são felizes os que andam', 'type' => 'text', 'attrs' => ['verseId' => 'PSA.119.1']],
                ],
            ],
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'q2'],
                'items' => [
                    ['text' => "em caminhos irrepreensíveis,\nque vivem conforme a lei do Senhor!", 'type' => 'text', 'attrs' => ['verseId' => 'PSA.119.1']],
                ],
            ],
        ];

        $parser = app(ApiBibleContentParser::class);
        $verses = $parser->parse($content, 'PSA', '119');

        expect($verses)->toHaveCount(1);
        $v1 = $verses->first();
        expect($v1->number)->toBe(1)
            ->and($v1->titles)->toHaveCount(1)
            ->and($v1->titles->first()->text)->toBe('Álef')
            ->and($v1->titles->first()->type)->toBe(VerseTitleTypeEnum::SECTION)
            ->and($v1->references)->toHaveCount(1)
            ->and($v1->references->first()->slug)->toBe('1')
            ->and($v1->references->first()->text)->toContain('poema organizado')
            ->and($v1->text)->toStartWith('{{1}}')
            ->and($v1->text)->toContain('Como são felizes os que andam');
    });

    it('inserts newline between paragraph breaks for same verse', function () {
        $content = [
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'q1'],
                'items' => [
                    ['name' => 'verse', 'type' => 'tag', 'attrs' => ['number' => '1', 'style' => 'v'], 'items' => []],
                    ['text' => 'First line', 'type' => 'text', 'attrs' => ['verseId' => 'PSA.119.1']],
                ],
            ],
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'q2'],
                'items' => [
                    ['text' => 'Second line', 'type' => 'text', 'attrs' => ['verseId' => 'PSA.119.1']],
                ],
            ],
        ];

        $parser = app(ApiBibleContentParser::class);
        $verses = $parser->parse($content, 'PSA', '119');

        expect($verses->first()->text)->toContain("\n")
            ->and($verses->first()->text)->toContain('First line')
            ->and($verses->first()->text)->toContain('Second line');
    });

    it('extracts inline note and inserts slug in verse text', function () {
        $content = [
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'q1'],
                'items' => [
                    ['name' => 'verse', 'type' => 'tag', 'attrs' => ['number' => '109', 'style' => 'v'], 'items' => []],
                    ['text' => 'A minha vida está sempre em perigo', 'type' => 'text', 'attrs' => ['verseId' => 'PSA.119.109']],
                    [
                        'name' => 'note',
                        'type' => 'tag',
                        'attrs' => ['style' => 'f', 'verseId' => 'PSA.119.109'],
                        'items' => [
                            [
                                'name' => 'char',
                                'type' => 'tag',
                                'attrs' => ['style' => 'ft'],
                                'items' => [['text' => 'Hebraico: em minhas mãos.', 'type' => 'text']],
                            ],
                        ],
                    ],
                    ['text' => ', mas não me esqueço da tua lei.', 'type' => 'text', 'attrs' => ['verseId' => 'PSA.119.109']],
                ],
            ],
        ];

        $parser = app(ApiBibleContentParser::class);
        $verses = $parser->parse($content, 'PSA', '119');

        expect($verses)->toHaveCount(1);
        $v = $verses->first();
        expect($v->number)->toBe(109)
            ->and($v->references->first()->text)->toBe('Hebraico: em minhas mãos.')
            ->and($v->text)->toContain('perigo{{1}}, mas');
    });
});
