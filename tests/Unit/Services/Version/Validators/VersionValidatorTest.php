<?php

use App\Services\Version\Validators\VersionValidator;
use App\Services\Version\DTOs\{VersionDTO, BookDTO, ChapterDTO, VerseDTO};
use App\Models\Version;
use App\Exceptions\Version\VersionImportException;

describe('VersionValidator', function () {
    beforeEach(function () {
        $this->validator = new VersionValidator();
    });

    describe('validateBeforeImport', function () {
        it('validates correct structure with 66 books', function () {
            $books = collect(range(1, 66))->map(fn($i) => 
                new BookDTO("Book {$i}", collect([
                    new ChapterDTO(1, collect([new VerseDTO(1, 'Text')]))
                ]))
            );

            $dto = new VersionDTO($books);

            expect(fn() => $this->validator->validateBeforeImport($dto))->not->toThrow(Exception::class);
        });

        it('throws exception for wrong books count', function () {
            $books = collect([new BookDTO('Genesis', collect([
                new ChapterDTO(1, collect([new VerseDTO(1, 'Text')]))
            ]))]);

            $this->validator->validateBeforeImport(new VersionDTO($books));
        })->throws(VersionImportException::class, 'Expected 66 books but got 1');

        it('throws exception for book without chapters', function () {
            $books = collect(range(1, 66))->map(fn($i) => 
                new BookDTO("Book {$i}", collect())
            );

            $this->validator->validateBeforeImport(new VersionDTO($books));
        })->throws(VersionImportException::class, 'is missing chapters');

        it('throws exception for chapter without verses', function () {
            $books = collect(range(1, 66))->map(fn($i) => 
                new BookDTO("Book {$i}", collect([new ChapterDTO(1, collect())]))
            );

            $this->validator->validateBeforeImport(new VersionDTO($books));
        })->throws(VersionImportException::class, 'is missing verses');

        it('throws exception for empty verse text', function () {
            $books = collect(range(1, 66))->map(fn($i) => 
                new BookDTO("Book {$i}", collect([
                    new ChapterDTO(1, collect([new VerseDTO(1, '   ')]))
                ]))
            );

            $this->validator->validateBeforeImport(new VersionDTO($books));
        })->throws(VersionImportException::class, 'has empty text');
    });

    describe('validateAfterImport', function () {
        it('does not throw exception', function () {
            $version = new Version();

            expect(fn() => $this->validator->validateAfterImport($version))->not->toThrow(Exception::class);
        });
    });
});
