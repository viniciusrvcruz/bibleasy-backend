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
        it('validates correct chapters and verses count', function () {
            $version = new Version();
            $version->chapters_count = 1189;
            $version->verses_count = 31102;

            expect(fn() => $this->validator->validateAfterImport($version))->not->toThrow(Exception::class);
        });

        it('throws exception for invalid chapters count', function () {
            $version = new Version();
            $version->chapters_count = 1000;
            $version->verses_count = 31102;

            $this->validator->validateAfterImport($version);
        })->throws(VersionImportException::class, 'Expected 1,189 chapters but got 1000');

        it('throws exception for verses count too low', function () {
            $version = new Version();
            $version->chapters_count = 1189;
            $version->verses_count = 30000;

            $this->validator->validateAfterImport($version);
        })->throws(VersionImportException::class, 'Expected verses between 31,100 and 31,110 but got 30000');

        it('throws exception for verses count too high', function () {
            $version = new Version();
            $version->chapters_count = 1189;
            $version->verses_count = 32000;

            $this->validator->validateAfterImport($version);
        })->throws(VersionImportException::class, 'Expected verses between 31,100 and 31,110 but got 32000');
    });
});
