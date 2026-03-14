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
        $verses = $parser->parse($content, 'PSA', '119', 'DEV');

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
        $verses = $parser->parse($content, 'PSA', '119', 'DEV');

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
        $verses = $parser->parse($content, 'PSA', '120', 'DEV');

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

    it('includes direct text nodes inside note in reference text', function () {
        // api.bible can put plain text items (type "text") as direct children of note alongside char tags (e.g. MRK.5.34 NVI)
        $content = [
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'p'],
                'items' => [
                    ['name' => 'verse', 'type' => 'tag', 'attrs' => ['number' => '34', 'style' => 'v'], 'items' => [['text' => '34', 'type' => 'text']]],
                    ['text' => 'Então ele lhe disse: "Filha, a sua fé a curou!', 'type' => 'text', 'attrs' => ['verseId' => 'MRK.5.34']],
                    [
                        'name' => 'note',
                        'type' => 'tag',
                        'attrs' => ['caller' => '+', 'style' => 'f', 'id' => 'MRK.5.34!f.1', 'verseId' => 'MRK.5.34'],
                        'items' => [
                            [
                                'name' => 'char',
                                'type' => 'tag',
                                'attrs' => ['style' => 'fr'],
                                'items' => [['text' => '5.34 ', 'type' => 'text']],
                            ],
                            ['text' => ' Ou ', 'type' => 'text'],
                            [
                                'name' => 'char',
                                'type' => 'tag',
                                'attrs' => ['style' => 'fqa', 'closed' => 'false'],
                                'items' => [['text' => 'a salvou!', 'type' => 'text']],
                            ],
                        ],
                    ],
                    ['text' => ' Vá em paz."', 'type' => 'text', 'attrs' => ['verseId' => 'MRK.5.34']],
                ],
            ],
        ];

        $parser = app(ApiBibleContentParser::class);
        $verses = $parser->parse($content, 'MRK', '5', 'NVI');

        expect($verses)->toHaveCount(1);
        $v = $verses->first();
        expect($v->number)->toBe(34)
            ->and($v->references)->toHaveCount(1)
            ->and($v->references->first()->text)->toBe('5.34  Ou a salvou!')
            ->and($v->text)->toContain('curou!{{1}} Vá em paz.');
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
        $verses = $parser->parse($content, 'PSA', '119', 'DEV');

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
        $verses = $parser->parse($content, 'TST', '1', 'DEV');

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
        $verses = $parser->parse($content, 'TST', '1', 'DEV');

        expect($verses)->toHaveCount(8);

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

        // Verse 2: text + cross-reference note (style x); note with direct text node (char + text + char); qa "Interlúdio" + note after verse -> title on verse 2 with position end; blank para (b) after adds extra newline
        $v2 = $verses->get(1);
        expect($v2->number)->toBe(2)
            ->and($v2->titles)->toHaveCount(1)
            ->and($v2->titles->get(0)->text)->toBe('Interlúdio{{3}}')
            ->and($v2->titles->get(0)->type)->toBe(VerseTitleTypeEnum::SECTION)
            ->and($v2->titles->get(0)->position)->toBe(VerseTitlePositionEnum::END)
            ->and($v2->text)->toContain('Paragrafo dois lorem ipsum')
            ->and($v2->text)->toContain('{{1}}')
            ->and($v2->text)->toContain('{{2}}')
            ->and($v2->text)->toContain("\n\n")
            ->and($v2->references)->toHaveCount(3)
            ->and($v2->references->get(0)->text)->toBe('Ref cruzada ficticia Xyz 2.1-3')
            ->and($v2->references->get(1)->slug)->toBe('2')
            ->and($v2->references->get(1)->text)->toContain('1.2 ')
            ->and($v2->references->get(1)->text)->toContain('ou')
            ->and($v2->references->get(1)->text)->toContain('variante com texto direto na nota.')
            ->and($v2->references->get(2)->slug)->toBe('3')
            ->and($v2->references->get(2)->text)->toContain('Selá. Termo musical ou literário');

        // Verses 3, 4, 5: multiple verses in same paragraph; verse 5 has continuation via para with vid
        $v3 = $verses->get(2);
        expect($v3->number)->toBe(3)->and($v3->text)->toContain('Verso tres consectetur');
        $v4 = $verses->get(3);
        expect($v4->number)->toBe(4)->and($v4->text)->toContain('Verso quatro sed do');
        $v5 = $verses->get(4);
        expect($v5->number)->toBe(5)
            ->and($v5->text)->toContain('Verso cinco incididunt')
            ->and($v5->text)->toContain('Continuacao verso cinco et dolore');

        // Verse 6: paragraph break (q1 then q2) inserts newline; char (sc) inline text; qa "Interlúdio" (no note) flushed when verse 7 tag is found -> title with position end
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

        // Verse 7: s1 title with note + sp (speaker) title with note -> both titles and references stay on verse 7 (position end)
        $v7 = $verses->get(6);
        expect($v7->number)->toBe(7)
            ->and($v7->titles)->toHaveCount(2)
            ->and($v7->titles->get(0)->text)->toBe('Titulo entre versos{{1}}')
            ->and($v7->titles->get(0)->type)->toBe(VerseTitleTypeEnum::SECTION)
            ->and($v7->titles->get(0)->position)->toBe(VerseTitlePositionEnum::END)
            ->and($v7->titles->get(1)->text)->toBe('A Amada{{2}}')
            ->and($v7->titles->get(1)->type)->toBe(VerseTitleTypeEnum::SECTION)
            ->and($v7->titles->get(1)->position)->toBe(VerseTitlePositionEnum::END)
            ->and($v7->references)->toHaveCount(2)
            ->and($v7->references->get(0)->slug)->toBe('1')
            ->and($v7->references->get(0)->text)->toBe('1.8 Nota do titulo com verseId apontando para o proximo verso.')
            ->and($v7->references->get(1)->slug)->toBe('2')
            ->and($v7->references->get(1)->text)->toContain('Os subtítulos identificam os interlocutores')
            ->and($v7->references->get(1)->text)->toContain('não fazem parte do texto original')
            ->and($v7->text)->toContain('Verso sete texto antes do titulo.');

        // Verse 8: must have no titles and no references (they belong to verse 7)
        $v8 = $verses->get(7);
        expect($v8->number)->toBe(8)
            ->and($v8->titles)->toHaveCount(0)
            ->and($v8->references)->toHaveCount(0)
            ->and($v8->text)->toContain('Verso oito texto apos o titulo.');
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
        $verses = $parser->parse($content, 'TST', '1', 'DEV');

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
        $verses = $parser->parse($content, 'TST', '1', 'DEV');

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

    it('attaches section title note reference to previous verse when note verseId points to next verse', function () {
        $content = [
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'q1'],
                'items' => [
                    ['name' => 'verse', 'type' => 'tag', 'attrs' => ['number' => '1', 'style' => 'v'], 'items' => [['text' => '1', 'type' => 'text']]],
                    ['text' => 'Cântico dos Cânticos de Salomão.', 'type' => 'text', 'attrs' => ['verseId' => 'SNG.1.1']],
                ],
            ],
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 's1'],
                'items' => [
                    ['text' => 'A Amada', 'type' => 'text'],
                    [
                        'name' => 'note',
                        'type' => 'tag',
                        'attrs' => ['caller' => '+', 'style' => 'f', 'id' => 'SNG.1.2!f.1', 'verseId' => 'SNG.1.2'],
                        'items' => [
                            [
                                'name' => 'char',
                                'type' => 'tag',
                                'attrs' => ['style' => 'fr', 'closed' => 'false'],
                                'items' => [['text' => '1.2 ', 'type' => 'text']],
                            ],
                            [
                                'name' => 'char',
                                'type' => 'tag',
                                'attrs' => ['style' => 'ft', 'closed' => 'false'],
                                'items' => [['text' => 'Nota explicativa sobre os interlocutores.', 'type' => 'text']],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'q1'],
                'items' => [
                    ['name' => 'verse', 'type' => 'tag', 'attrs' => ['number' => '2', 'style' => 'v'], 'items' => [['text' => '2', 'type' => 'text']]],
                    ['text' => 'Ah, se ele me beijasse.', 'type' => 'text', 'attrs' => ['verseId' => 'SNG.1.2']],
                ],
            ],
        ];

        $parser = app(ApiBibleContentParser::class);
        $verses = $parser->parse($content, 'SNG', '1', 'DEV');

        expect($verses)->toHaveCount(2);

        $v1 = $verses->get(0);
        expect($v1->number)->toBe(1)
            ->and($v1->titles)->toHaveCount(1)
            ->and($v1->titles->first()->text)->toBe('A Amada{{1}}')
            ->and($v1->titles->first()->type)->toBe(VerseTitleTypeEnum::SECTION)
            ->and($v1->titles->first()->position)->toBe(VerseTitlePositionEnum::END)
            ->and($v1->references)->toHaveCount(1)
            ->and($v1->references->first()->slug)->toBe('1')
            ->and($v1->references->first()->text)->toContain('Nota explicativa sobre os interlocutores.')
            ->and($v1->text)->toContain('Cântico dos Cânticos de Salomão.');

        $v2 = $verses->get(1);
        expect($v2->number)->toBe(2)
            ->and($v2->titles)->toHaveCount(0)
            ->and($v2->references)->toHaveCount(0)
            ->and($v2->text)->toContain('Ah, se ele me beijasse.');
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
        $verses = $parser->parse($content, 'TST', '1', 'DEV');

        expect($verses)->toHaveCount(1);
        $v1 = $verses->first();
        expect($v1->titles)->toHaveCount(1)
            ->and($v1->titles->first()->text)->toBe('Interlúdio')
            ->and($v1->titles->first()->position)->toBe(VerseTitlePositionEnum::END);
    });

    it('assigns position custom with slug to speaker title (sp) that appears mid-verse', function () {
        $content = [
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'q'],
                'items' => [
                    ['name' => 'verse', 'type' => 'tag', 'attrs' => ['number' => '4', 'style' => 'v'], 'items' => [['text' => '4', 'type' => 'text']]],
                    ['text' => 'Leve-me com você! Vamos correr juntos!', 'type' => 'text', 'attrs' => ['verseId' => 'SNG.1.4']],
                ],
            ],
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'sp', 'vid' => 'SNG 1:4'],
                'items' => [
                    ['text' => 'As mulheres de Jerusalém', 'type' => 'text'],
                ],
            ],
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'q', 'vid' => 'SNG 1:4'],
                'items' => [
                    ['text' => 'Ó rei, estamos alegres e felizes por sua causa!', 'type' => 'text', 'attrs' => ['verseId' => 'SNG.1.4']],
                ],
            ],
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'sp', 'vid' => 'SNG 1:4'],
                'items' => [
                    ['text' => 'A Amada', 'type' => 'text'],
                ],
            ],
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'q', 'vid' => 'SNG 1:4'],
                'items' => [
                    ['text' => 'Com razão elas o amam.', 'type' => 'text', 'attrs' => ['verseId' => 'SNG.1.4']],
                ],
            ],
        ];

        $parser = app(ApiBibleContentParser::class);
        $verses = $parser->parse($content, 'SNG', '1', 'DEV');

        expect($verses)->toHaveCount(1);
        $v4 = $verses->first();
        expect($v4->number)->toBe(4)
            ->and($v4->titles)->toHaveCount(2)
            ->and($v4->titles->get(0)->text)->toBe('As mulheres de Jerusalém')
            ->and($v4->titles->get(0)->type)->toBe(VerseTitleTypeEnum::SECTION)
            ->and($v4->titles->get(0)->position)->toBe(VerseTitlePositionEnum::CUSTOM)
            ->and($v4->titles->get(0)->slug)->toBe('1')
            ->and($v4->titles->get(1)->text)->toBe('A Amada')
            ->and($v4->titles->get(1)->type)->toBe(VerseTitleTypeEnum::SECTION)
            ->and($v4->titles->get(1)->position)->toBe(VerseTitlePositionEnum::CUSTOM)
            ->and($v4->titles->get(1)->slug)->toBe('2')
            ->and($v4->text)->toContain('[[1]]')
            ->and($v4->text)->toContain('[[2]]')
            ->and($v4->text)->toContain('Leve-me com você!')
            ->and($v4->text)->toContain('Ó rei, estamos alegres')
            ->and($v4->text)->toContain('Com razão elas o amam.');
    });

    it('buffers speaker title (sp) as regular section title when no verse is active yet', function () {
        $content = [
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'sp'],
                'items' => [
                    ['text' => 'A Amada', 'type' => 'text'],
                ],
            ],
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'q'],
                'items' => [
                    ['name' => 'verse', 'type' => 'tag', 'attrs' => ['number' => '2', 'style' => 'v'], 'items' => [['text' => '2', 'type' => 'text']]],
                    ['text' => 'Ah, se ele me beijasse com beijos de sua boca!', 'type' => 'text', 'attrs' => ['verseId' => 'SNG.1.2']],
                ],
            ],
        ];

        $parser = app(ApiBibleContentParser::class);
        $verses = $parser->parse($content, 'SNG', '1', 'DEV');

        expect($verses)->toHaveCount(1);
        $v2 = $verses->first();
        expect($v2->number)->toBe(2)
            ->and($v2->titles)->toHaveCount(1)
            ->and($v2->titles->first()->text)->toBe('A Amada')
            ->and($v2->titles->first()->type)->toBe(VerseTitleTypeEnum::SECTION)
            ->and($v2->titles->first()->position)->toBe(VerseTitlePositionEnum::START)
            ->and($v2->titles->first()->slug)->toBeNull()
            ->and($v2->text)->toContain('Ah, se ele me beijasse');
    });

    it('assigns position end to speaker title (sp) between two different verses', function () {
        $content = [
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'q'],
                'items' => [
                    ['name' => 'verse', 'type' => 'tag', 'attrs' => ['number' => '7', 'style' => 'v'], 'items' => [['text' => '7', 'type' => 'text']]],
                    ['text' => 'Texto do verso sete.', 'type' => 'text', 'attrs' => ['verseId' => 'SNG.1.7']],
                ],
            ],
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'sp'],
                'items' => [
                    ['text' => 'O Amado', 'type' => 'text'],
                ],
            ],
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'q'],
                'items' => [
                    ['name' => 'verse', 'type' => 'tag', 'attrs' => ['number' => '8', 'style' => 'v'], 'items' => [['text' => '8', 'type' => 'text']]],
                    ['text' => 'Texto do verso oito.', 'type' => 'text', 'attrs' => ['verseId' => 'SNG.1.8']],
                ],
            ],
        ];

        $parser = app(ApiBibleContentParser::class);
        $verses = $parser->parse($content, 'SNG', '1', 'DEV');

        expect($verses)->toHaveCount(2);
        $v7 = $verses->get(0);
        expect($v7->number)->toBe(7)
            ->and($v7->titles)->toHaveCount(1)
            ->and($v7->titles->first()->text)->toBe('O Amado')
            ->and($v7->titles->first()->type)->toBe(VerseTitleTypeEnum::SECTION)
            ->and($v7->titles->first()->position)->toBe(VerseTitlePositionEnum::END)
            ->and($v7->titles->first()->slug)->toBeNull();

        $v8 = $verses->get(1);
        expect($v8->number)->toBe(8)
            ->and($v8->titles)->toHaveCount(0);
    });

    it('processes note inside speaker title (sp) and adds reference to verse that receives the title', function () {
        // Mirrors api.bible NVT SNG.1: "A Amada" subtitle with footnote about interlocutors
        $content = [
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'p'],
                'items' => [
                    ['name' => 'verse', 'type' => 'tag', 'attrs' => ['number' => '1', 'style' => 'v'], 'items' => [['text' => '1', 'type' => 'text']]],
                    ['text' => 'Este é o cântico dos cânticos de Salomão.', 'type' => 'text', 'attrs' => ['verseId' => 'SNG.1.1']],
                ],
            ],
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'sp'],
                'items' => [
                    ['text' => 'A Amada', 'type' => 'text'],
                    [
                        'name' => 'note',
                        'type' => 'tag',
                        'attrs' => ['caller' => '-', 'style' => 'f', 'id' => 'SNG.1.2!f.1', 'verseId' => 'SNG.1.2'],
                        'items' => [
                            [
                                'name' => 'char',
                                'type' => 'tag',
                                'attrs' => ['style' => 'fr', 'closed' => 'false'],
                                'items' => [['text' => '1.1 ', 'type' => 'text']],
                            ],
                            [
                                'name' => 'char',
                                'type' => 'tag',
                                'attrs' => ['style' => 'ft', 'closed' => 'false'],
                                'items' => [
                                    [
                                        'text' => 'Os subtítulos identificam os interlocutores e não fazem parte do texto original, embora o hebraico geralmente forneça indicações por meio do gênero de quem fala.',
                                        'type' => 'text',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'para',
                'type' => 'tag',
                'attrs' => ['style' => 'q'],
                'items' => [
                    ['name' => 'verse', 'type' => 'tag', 'attrs' => ['number' => '2', 'style' => 'v'], 'items' => [['text' => '2', 'type' => 'text']]],
                    ['text' => 'Beije-me, beije-me mais uma vez,', 'type' => 'text', 'attrs' => ['verseId' => 'SNG.1.2']],
                ],
            ],
        ];

        $parser = app(ApiBibleContentParser::class);
        $verses = $parser->parse($content, 'SNG', '1', 'DEV');

        expect($verses)->toHaveCount(2);

        $v1 = $verses->get(0);
        expect($v1->number)->toBe(1)
            ->and($v1->titles)->toHaveCount(1)
            ->and($v1->titles->first()->text)->toBe('A Amada{{1}}')
            ->and($v1->titles->first()->type)->toBe(VerseTitleTypeEnum::SECTION)
            ->and($v1->titles->first()->position)->toBe(VerseTitlePositionEnum::END)
            ->and($v1->references)->toHaveCount(1)
            ->and($v1->references->first()->slug)->toBe('1')
            ->and($v1->references->first()->text)->toContain('Os subtítulos identificam os interlocutores')
            ->and($v1->references->first()->text)->toContain('não fazem parte do texto original')
            ->and($v1->text)->toContain('Este é o cântico dos cânticos de Salomão.');

        $v2 = $verses->get(1);
        expect($v2->number)->toBe(2)
            ->and($v2->titles)->toHaveCount(0)
            ->and($v2->references)->toHaveCount(0)
            ->and($v2->text)->toContain('Beije-me, beije-me mais uma vez,');
    });
});
