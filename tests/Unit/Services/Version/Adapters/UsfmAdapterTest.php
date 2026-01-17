<?php

use App\Enums\BookAbbreviationEnum;
use App\Services\Version\Adapters\UsfmAdapter;
use App\Services\Version\DTOs\FileDTO;
use App\Services\Version\DTOs\VersionDTO;
use App\Exceptions\Version\VersionImportException;

describe('UsfmAdapter', function () {
    it('adapts valid USFM file successfully', function () {
        $usfmContent = <<<'USFM'
\h Mateus
\c 1
\p
\v 1 Este livro é o registro da genealogia de Jesus Cristo\f + \fr 1:1 \ft Ou "Cristo." Messias é a palavra em hebraico para Cristo em grego.\f*, filho de Davi, filho de Abraão:
\v 2 Abraão gerou Isaque; Isaque gerou Jacó;
USFM;

        $file = new FileDTO(
            content: $usfmContent,
            fileName: 'mat.usfm',
            extension: 'usfm'
        );

        $adapter = app(UsfmAdapter::class);
        $result = $adapter->adapt([$file]);

        expect($result)->toBeInstanceOf(VersionDTO::class)
            ->and($result->books)->toHaveCount(1)
            ->and($result->books->first()->name)->toBe('Mateus')
            ->and($result->books->first()->abbreviation)->toBe(BookAbbreviationEnum::MAT)
            ->and($result->books->first()->chapters)->toHaveCount(1)
            ->and($result->books->first()->chapters->first()->number)->toBe(1)
            ->and($result->books->first()->chapters->first()->verses)->toHaveCount(2);
    });

    it('extracts book name from \h marker', function () {
        $usfmContent = <<<'USFM'
\h Gênesis
\c 1
\v 1 No princípio criou Deus os céus e a terra.
USFM;

        $file = new FileDTO(
            content: $usfmContent,
            fileName: 'gen.usfm',
            extension: 'usfm'
        );

        $adapter = app(UsfmAdapter::class);
        $result = $adapter->adapt([$file]);

        expect($result->books->first()->name)->toBe('Gênesis');
    });

    it('extracts chapters from \c marker', function () {
        $usfmContent = <<<'USFM'
\h Mateus
\c 1
\v 1 Versículo do capítulo 1
\c 2
\v 1 Versículo do capítulo 2
USFM;

        $file = new FileDTO(
            content: $usfmContent,
            fileName: 'mat.usfm',
            extension: 'usfm'
        );

        $adapter = app(UsfmAdapter::class);
        $result = $adapter->adapt([$file]);

        expect($result->books->first()->chapters)->toHaveCount(2)
            ->and($result->books->first()->chapters->first()->number)->toBe(1)
            ->and($result->books->first()->chapters->last()->number)->toBe(2);
    });

    it('extracts verses from \v marker', function () {
        $usfmContent = <<<'USFM'
\h Mateus
\c 1
\v 1 Primeiro versículo
\v 2 Segundo versículo
\v 3 Terceiro versículo
USFM;

        $file = new FileDTO(
            content: $usfmContent,
            fileName: 'mat.usfm',
            extension: 'usfm'
        );

        $adapter = app(UsfmAdapter::class);
        $result = $adapter->adapt([$file]);

        $verses = $result->books->first()->chapters->first()->verses;

        expect($verses)->toHaveCount(3)
            ->and($verses->get(0)->number)->toBe(1)
            ->and($verses->get(0)->text)->toBe('Primeiro versículo')
            ->and($verses->get(1)->number)->toBe(2)
            ->and($verses->get(2)->number)->toBe(3);
    });

    it('extracts and processes references', function () {
        $usfmContent = <<<'USFM'
\h Mateus
\c 1
\v 1 Este livro é o registro\f + \fr 1:1 \ft Ou "Cristo." Messias é a palavra em hebraico para Cristo em grego.\f* da genealogia
USFM;

        $file = new FileDTO(
            content: $usfmContent,
            fileName: 'mat.usfm',
            extension: 'usfm'
        );

        $adapter = app(UsfmAdapter::class);
        $result = $adapter->adapt([$file]);

        $verse = $result->books->first()->chapters->first()->verses->first();

        expect($verse->references)->toHaveCount(1)
            ->and($verse->references->first()->slug)->toBe('1')
            ->and($verse->references->first()->text)->toBe('Ou "Cristo." Messias é a palavra em hebraico para Cristo em grego.')
            ->and($verse->text)->toContain('{{1}}');
    });

    it('replaces multiple references with slugs in text', function () {
        $usfmContent = <<<'USFM'
\h Mateus
\c 1
\v 1 Primeira referência\f + \fr 1:1 \ft Texto da primeira referência\f* e segunda referência\f + \fr 1:2 \ft Texto da segunda referência\f* no mesmo versículo
USFM;

        $file = new FileDTO(
            content: $usfmContent,
            fileName: 'mat.usfm',
            extension: 'usfm'
        );

        $adapter = app(UsfmAdapter::class);
        $result = $adapter->adapt([$file]);

        $verse = $result->books->first()->chapters->first()->verses->first();

        expect($verse->references)->toHaveCount(2)
            ->and($verse->text)->toContain('{{1}}')
            ->and($verse->text)->toContain('{{2}}')
            ->and($verse->references->get(0)->slug)->toBe('1')
            ->and($verse->references->get(1)->slug)->toBe('2');
    });

    it('adds newline to last verse when \p marker is found', function () {
        $usfmContent = <<<'USFM'
\h Mateus
\c 1
\v 1 Primeiro versículo do parágrafo
\p
\v 2 Segundo versículo do novo parágrafo
USFM;

        $file = new FileDTO(
            content: $usfmContent,
            fileName: 'mat.usfm',
            extension: 'usfm'
        );

        $adapter = app(UsfmAdapter::class);
        $result = $adapter->adapt([$file]);

        $verses = $result->books->first()->chapters->first()->verses;
        
        expect($verses)->not->toBeNull()
            ->and($verses)->not->toBeEmpty()
            ->and($verses->first()->text)->toEndWith("\n");
    });

    it('validates file extension', function () {
        $file = new FileDTO(
            content: 'content',
            fileName: 'mat.json',
            extension: 'json'
        );

        $adapter = app(UsfmAdapter::class);

        expect(fn() => $adapter->adapt([$file]))
            ->toThrow(VersionImportException::class, 'File must have .usfm extension');
    });

    it('validates file name matches book abbreviation', function () {
        $file = new FileDTO(
            content: '\h Mateus\n\c 1\n\v 1 Texto',
            fileName: 'invalid.usfm',
            extension: 'usfm'
        );

        $adapter = app(UsfmAdapter::class);

        expect(fn() => $adapter->adapt([$file]))
            ->toThrow(VersionImportException::class, "File name 'invalid.usfm' does not match any book abbreviation");
    });

    it('throws exception when book name is missing', function () {
        $usfmContent = <<<'USFM'
\c 1
\v 1 Versículo sem nome do livro
USFM;

        $file = new FileDTO(
            content: $usfmContent,
            fileName: 'mat.usfm',
            extension: 'usfm'
        );

        $adapter = app(UsfmAdapter::class);

        expect(fn() => $adapter->adapt([$file]))
            ->toThrow(VersionImportException::class, 'Book name (\\h marker) not found in USFM file');
    });

    it('processes multiple files', function () {
        $file1 = new FileDTO(
            content: "\h Mateus\n\c 1\n\v 1 Versículo de Mateus",
            fileName: 'mat.usfm',
            extension: 'usfm'
        );

        $file2 = new FileDTO(
            content: "\h Marcos\n\c 1\n\v 1 Versículo de Marcos",
            fileName: 'mrk.usfm',
            extension: 'usfm'
        );

        $adapter = app(UsfmAdapter::class);
        $result = $adapter->adapt([$file1, $file2]);

        expect($result->books)->toHaveCount(2)
            ->and($result->books->get(0)->name)->toBe('Mateus')
            ->and($result->books->get(1)->name)->toBe('Marcos');
    });

    it('cleans verse text by removing reference markers', function () {
        $usfmContent = <<<'USFM'
\h Mateus
\c 1
\v 1 Texto limpo\f + \fr 1:1 \ft Referência\f* continuação do texto
USFM;

        $file = new FileDTO(
            content: $usfmContent,
            fileName: 'mat.usfm',
            extension: 'usfm'
        );

        $adapter = app(UsfmAdapter::class);
        $result = $adapter->adapt([$file]);

        $verse = $result->books->first()->chapters->first()->verses->first();

        expect($verse->text)->not->toContain('\f')
            ->and($verse->text)->not->toContain('\fr')
            ->and($verse->text)->not->toContain('\ft')
            ->and($verse->text)->toContain('{{1}}');
    });

    it('handles case insensitive file name', function () {
        $usfmContent = <<<'USFM'
\h Mateus
\c 1
\v 1 Versículo
USFM;

        $file = new FileDTO(
            content: $usfmContent,
            fileName: 'MAT.usfm',
            extension: 'usfm'
        );

        $adapter = app(UsfmAdapter::class);
        $result = $adapter->adapt([$file]);

        expect($result->books->first()->abbreviation)->toBe(BookAbbreviationEnum::MAT);
    });

    it('removes formatting markers from verse text', function () {
        $usfmContent = <<<'USFM'
\h Mateus
\c 8
\v 27 Os discípulos ficaram admirados e disseram: "Quem \it é\it* este? Até mesmo os ventos e as ondas lhe obedecem!"
USFM;

        $file = new FileDTO(
            content: $usfmContent,
            fileName: 'mat.usfm',
            extension: 'usfm'
        );

        $adapter = app(UsfmAdapter::class);
        $result = $adapter->adapt([$file]);

        $verse = $result->books->first()->chapters->first()->verses->first();

        expect($verse->text)->not->toContain('\it')
            ->and($verse->text)->not->toContain('\it*')
            ->and($verse->text)->toContain('é')
            ->and($verse->text)->toContain('este?');
    });

    it('removes multiple formatting markers from verse text', function () {
        $usfmContent = <<<'USFM'
\h Mateus
\c 1
\v 1 Texto com \it itálico\it* e \bd negrito\bd* e \em ênfase\em*
USFM;

        $file = new FileDTO(
            content: $usfmContent,
            fileName: 'mat.usfm',
            extension: 'usfm'
        );

        $adapter = app(UsfmAdapter::class);
        $result = $adapter->adapt([$file]);

        $verse = $result->books->first()->chapters->first()->verses->first();

        expect($verse->text)->not->toContain('\it')
            ->and($verse->text)->not->toContain('\it*')
            ->and($verse->text)->not->toContain('\bd')
            ->and($verse->text)->not->toContain('\bd*')
            ->and($verse->text)->not->toContain('\em')
            ->and($verse->text)->not->toContain('\em*')
            ->and($verse->text)->toContain('itálico')
            ->and($verse->text)->toContain('negrito')
            ->and($verse->text)->toContain('ênfase');
    });
});
