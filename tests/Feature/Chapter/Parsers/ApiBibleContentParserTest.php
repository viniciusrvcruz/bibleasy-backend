<?php

use App\Enums\VerseTitlePositionEnum;
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
            ->and($v1->titles->first()->position)->toBe(VerseTitlePositionEnum::START)
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

    it('extracts note inside section title and adds placeholder to title and reference to verse', function () {
        $content = [
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'd'],
                'items' => [
                    ['text' => 'Cântico ', 'type' => 'text'],
                    [
                        'name' => 'note',
                        'type' => 'tag',
                        'attrs' => ['style' => 'f', 'verseId' => 'PSA.120.1'],
                        'items' => [
                            [
                                'name' => 'char',
                                'type' => 'tag',
                                'attrs' => ['style' => 'ft'],
                                'items' => [['text' => 'Ou dos Degraus; também nos Sal 121.1 a Sal 134.1.', 'type' => 'text']],
                            ],
                        ],
                    ],
                    ['text' => ' de peregrinação.', 'type' => 'text'],
                ],
            ],
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'q1'],
                'items' => [
                    ['name' => 'verse', 'type' => 'tag', 'attrs' => ['number' => '1', 'style' => 'v'], 'items' => [['text' => '1', 'type' => 'text']]],
                    ['text' => 'Na minha angústia clamei ao Senhor.', 'type' => 'text', 'attrs' => ['verseId' => 'PSA.120.1']],
                ],
            ],
        ];

        $parser = app(ApiBibleContentParser::class);
        $verses = $parser->parse($content, 'PSA', '120');

        expect($verses)->toHaveCount(1);
        $v1 = $verses->first();
        expect($v1->number)->toBe(1)
            ->and($v1->titles)->toHaveCount(1)
            ->and($v1->titles->first()->text)->toBe('Cântico {{1}} de peregrinação.')
            ->and($v1->titles->first()->type)->toBe(VerseTitleTypeEnum::SECTION)
            ->and($v1->titles->first()->position)->toBe(VerseTitlePositionEnum::START)
            ->and($v1->references)->toHaveCount(1)
            ->and($v1->references->first()->slug)->toBe('1')
            ->and($v1->references->first()->text)->toBe('Ou dos Degraus; também nos Sal 121.1 a Sal 134.1.')
            ->and($v1->text)->toContain('Na minha angústia clamei ao Senhor.');
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

    it('adds newline when blank paragraph (style b) is encountered', function () {
        $content = [
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'p'],
                'items' => [
                    ['name' => 'verse', 'type' => 'tag', 'attrs' => ['number' => '1', 'style' => 'v'], 'items' => [['text' => '1', 'type' => 'text']]],
                    ['text' => 'First verse line.', 'type' => 'text', 'attrs' => ['verseId' => 'TST.1.1']],
                ],
            ],
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'b'],
                'items' => []
            ],
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'p'],
                'items' => [
                    ['name' => 'verse', 'type' => 'tag', 'attrs' => ['number' => '2', 'style' => 'v'], 'items' => [['text' => '2', 'type' => 'text']]],
                    ['text' => 'Second verse after blank.', 'type' => 'text', 'attrs' => ['verseId' => 'TST.1.2']],
                ],
            ],
        ];

        $parser = app(ApiBibleContentParser::class);
        $verses = $parser->parse($content, 'TST', '1');

        expect($verses)->toHaveCount(2);
        $v1 = $verses->get(0);
        $v2 = $verses->get(1);
        expect($v1->text)->toEndWith("\n\n")
            ->and($v1->text)->toContain('First verse line.')
            ->and($v2->text)->toContain('Second verse after blank.');
    });

    it('parses unified api.bible fixture with all structure variants', function () {
        $path = dirname(__DIR__, 3) . '/Fixtures/api_bible_chapter_content.json';
        $json = json_decode(file_get_contents($path), true);
        $content = $json['data']['content'];

        $parser = app(ApiBibleContentParser::class);
        $verses = $parser->parse($content, 'TST', '1');

        expect($verses)->toHaveCount(6);

        // Verse 1: section title (s1) with note placeholder, reference title (r), chapter-label note (ref_prefix), text, inline footnote; titles before first verse have position start
        $v1 = $verses->get(0);
        expect($v1->number)->toBe(1)
            ->and($v1->titles)->toHaveCount(2)
            ->and($v1->titles->get(0)->text)->toBe('Titulo secao {{2}} fixture')
            ->and($v1->titles->get(0)->type)->toBe(VerseTitleTypeEnum::SECTION)
            ->and($v1->titles->get(0)->position)->toBe(VerseTitlePositionEnum::START)
            ->and($v1->titles->get(1)->text)->toBe('Titulo referencia fixture')
            ->and($v1->titles->get(1)->type)->toBe(VerseTitleTypeEnum::REFERENCE)
            ->and($v1->titles->get(1)->position)->toBe(VerseTitlePositionEnum::START)
            ->and($v1->text)->toStartWith('{{1}}')
            ->and($v1->text)->toContain('Texto verso um corpo principal')
            ->and($v1->text)->toContain('{{3}}')
            ->and($v1->references)->toHaveCount(3)
            ->and($v1->references->get(0)->slug)->toBe('1')
            ->and($v1->references->get(0)->text)->toContain('Nota sintética no cl')
            ->and($v1->references->get(1)->slug)->toBe('2')
            ->and($v1->references->get(1)->text)->toBe('Nota dentro do titulo de secao.')
            ->and($v1->references->get(2)->slug)->toBe('3')
            ->and($v1->references->get(2)->text)->toContain('Variante alternativa sintetica');

        // Verse 2: text + cross-reference note (style x); qa "Interlúdio" + note after verse -> title on verse 2 with position end; blank para (b) after adds extra newline
        $v2 = $verses->get(1);
        expect($v2->number)->toBe(2)
            ->and($v2->titles)->toHaveCount(1)
            ->and($v2->titles->get(0)->text)->toBe('Interlúdio{{2}}')
            ->and($v2->titles->get(0)->type)->toBe(VerseTitleTypeEnum::SECTION)
            ->and($v2->titles->get(0)->position)->toBe(VerseTitlePositionEnum::END)
            ->and($v2->text)->toContain('Paragrafo dois lorem ipsum')
            ->and($v2->text)->toContain('{{1}}')
            ->and($v2->text)->toContain("\n\n")
            ->and($v2->references)->toHaveCount(2)
            ->and($v2->references->get(0)->text)->toBe('Ref cruzada ficticia Xyz 2.1-3')
            ->and($v2->references->get(1)->slug)->toBe('2')
            ->and($v2->references->get(1)->text)->toContain('Selá. Termo musical ou literário');

        // Verses 3, 4, 5: multiple verses in same paragraph; verse 5 has continuation via para with vid
        $v3 = $verses->get(2);
        expect($v3->number)->toBe(3)->and($v3->text)->toContain('Verso tres consectetur');
        $v4 = $verses->get(3);
        expect($v4->number)->toBe(4)->and($v4->text)->toContain('Verso quatro sed do');
        $v5 = $verses->get(4);
        expect($v5->number)->toBe(5)
            ->and($v5->text)->toContain('Verso cinco incididunt')
            ->and($v5->text)->toContain('Continuacao verso cinco et dolore');

        // Verse 6: paragraph break (q1 then q2) inserts newline; char (sc) inline text; last qa "Interlúdio" (no note) flushed at end -> title with position end
        $v6 = $verses->get(5);
        expect($v6->number)->toBe(6)
            ->and($v6->titles)->toHaveCount(1)
            ->and($v6->titles->get(0)->text)->toBe('Interlúdio')
            ->and($v6->titles->get(0)->type)->toBe(VerseTitleTypeEnum::SECTION)
            ->and($v6->titles->get(0)->position)->toBe(VerseTitlePositionEnum::END)
            ->and($v6->text)->toContain('Verso seis primeira linha')
            ->and($v6->text)->toContain("\n")
            ->and($v6->text)->toContain('Segunda linha')
            ->and($v6->text)->toContain('Destaque')
            ->and($v6->text)->toContain('fim do verso seis');
    });

    it('assigns position start to titles that appear before the first verse', function () {
        $content = [
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'd'],
                'items' => [['text' => 'Título antes do verso um', 'type' => 'text']],
            ],
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'p'],
                'items' => [
                    ['name' => 'verse', 'type' => 'tag', 'attrs' => ['number' => '1', 'style' => 'v'], 'items' => [['text' => '1', 'type' => 'text']]],
                    ['text' => 'Texto do verso um.', 'type' => 'text', 'attrs' => ['verseId' => 'TST.1.1']],
                ],
            ],
        ];

        $parser = app(ApiBibleContentParser::class);
        $verses = $parser->parse($content, 'TST', '1');

        expect($verses)->toHaveCount(1);
        $v1 = $verses->first();
        expect($v1->titles)->toHaveCount(1)
            ->and($v1->titles->first()->text)->toBe('Título antes do verso um')
            ->and($v1->titles->first()->position)->toBe(VerseTitlePositionEnum::START);
    });

    it('assigns position end to section title (qa) that appears after a verse and attaches it to previous verse', function () {
        $content = [
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'p'],
                'items' => [
                    ['name' => 'verse', 'type' => 'tag', 'attrs' => ['number' => '1', 'style' => 'v'], 'items' => [['text' => '1', 'type' => 'text']]],
                    ['text' => 'Verso um.', 'type' => 'text', 'attrs' => ['verseId' => 'TST.1.1']],
                ],
            ],
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'qa'],
                'items' => [
                    ['text' => 'Interlúdio', 'type' => 'text', 'attrs' => ['verseId' => 'TST.1.1', 'verseOrgIds' => ['TST.1.2']]],
                    [
                        'name' => 'note',
                        'type' => 'tag',
                        'attrs' => ['style' => 'f', 'verseId' => 'TST.1.1'],
                        'items' => [
                            [
                                'name' => 'char',
                                'type' => 'tag',
                                'attrs' => ['style' => 'ft'],
                                'items' => [['text' => 'Nota do interlúdio.', 'type' => 'text']],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'p'],
                'items' => [
                    ['name' => 'verse', 'type' => 'tag', 'attrs' => ['number' => '2', 'style' => 'v'], 'items' => [['text' => '2', 'type' => 'text']]],
                    ['text' => 'Verso dois.', 'type' => 'text', 'attrs' => ['verseId' => 'TST.1.2']],
                ],
            ],
        ];

        $parser = app(ApiBibleContentParser::class);
        $verses = $parser->parse($content, 'TST', '1');

        expect($verses)->toHaveCount(2);
        $v1 = $verses->get(0);
        expect($v1->titles)->toHaveCount(1)
            ->and($v1->titles->first()->text)->toBe('Interlúdio{{1}}')
            ->and($v1->titles->first()->position)->toBe(VerseTitlePositionEnum::END)
            ->and($v1->references)->toHaveCount(1)
            ->and($v1->references->first()->text)->toBe('Nota do interlúdio.');
        $v2 = $verses->get(1);
        expect($v2->titles)->toHaveCount(0)
            ->and($v2->text)->toContain('Verso dois.');
    });

    it('attaches remaining titles at end of chapter to last verse with position end', function () {
        $content = [
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'p'],
                'items' => [
                    ['name' => 'verse', 'type' => 'tag', 'attrs' => ['number' => '1', 'style' => 'v'], 'items' => [['text' => '1', 'type' => 'text']]],
                    ['text' => 'Único verso.', 'type' => 'text', 'attrs' => ['verseId' => 'TST.1.1']],
                ],
            ],
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'qa'],
                'items' => [
                    ['text' => 'Interlúdio', 'type' => 'text', 'attrs' => ['verseId' => 'TST.1.1', 'verseOrgIds' => ['TST.1.2']]],
                ],
            ],
        ];

        $parser = app(ApiBibleContentParser::class);
        $verses = $parser->parse($content, 'TST', '1');

        expect($verses)->toHaveCount(1);
        $v1 = $verses->first();
        expect($v1->titles)->toHaveCount(1)
            ->and($v1->titles->first()->text)->toBe('Interlúdio')
            ->and($v1->titles->first()->position)->toBe(VerseTitlePositionEnum::END);
    });
});
