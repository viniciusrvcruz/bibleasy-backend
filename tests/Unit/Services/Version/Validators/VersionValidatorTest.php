<?php

use App\Services\Version\Validators\VersionValidator;
use App\Services\Version\DTOs\{VersionDTO, BookDTO, ChapterDTO, VerseDTO, VerseReferenceDTO};
use App\Enums\BookAbbreviationEnum;
use App\Enums\VersionTextSourceEnum;
use App\Exceptions\Version\VersionImportException;

describe('VersionValidator', function () {
    beforeEach(function () {
        $this->validator = new VersionValidator();
    });

    it('validates correct structure', function () {
        $books = collect([
            new BookDTO(
                'Genesis',
                BookAbbreviationEnum::GEN,
                collect([
                    new ChapterDTO(1, collect([new VerseDTO(1, 'Text')]))
                ])
            )
        ]);

        $dto = new VersionDTO($books);

        expect(fn() => $this->validator->validate($dto, VersionTextSourceEnum::DATABASE))->not->toThrow(Exception::class);
    });

    it('throws exception for book without chapters', function () {
        $books = collect([
            new BookDTO(
                'Genesis',
                BookAbbreviationEnum::GEN,
                collect()
            )
        ]);

        $this->validator->validate(new VersionDTO($books), VersionTextSourceEnum::DATABASE);
    })->throws(VersionImportException::class, 'is missing chapters');

    it('throws exception for chapter without verses', function () {
        $books = collect([
            new BookDTO(
                'Genesis',
                BookAbbreviationEnum::GEN,
                collect([new ChapterDTO(1, collect())])
            )
        ]);

        $this->validator->validate(new VersionDTO($books), VersionTextSourceEnum::DATABASE);
    })->throws(VersionImportException::class, 'is missing verses');

    it('throws exception for empty verse text', function () {
        $books = collect([
            new BookDTO(
                'Genesis',
                BookAbbreviationEnum::GEN,
                collect([
                    new ChapterDTO(1, collect([new VerseDTO(1, '   ')]))
                ])
            )
        ]);

        $this->validator->validate(new VersionDTO($books), VersionTextSourceEnum::DATABASE);
    })->throws(VersionImportException::class, 'has empty text');

    it('throws exception when book is not an instance of BookDTO', function () {
        $books = collect([
            (object) [
                'name' => 'Genesis',
                'chapters' => collect()
            ]
        ]);

        $dto = new VersionDTO($books);

        $this->validator->validate($dto, VersionTextSourceEnum::DATABASE);
    })->throws(VersionImportException::class, 'is not an instance of BookDTO');

    it('throws exception when chapter is not an instance of ChapterDTO', function () {
        $books = collect([
            new BookDTO(
                'Genesis',
                BookAbbreviationEnum::GEN,
                collect([
                    (object) ['number' => 1, 'verses' => collect()]
                ])
            )
        ]);

        $dto = new VersionDTO($books);

        $this->validator->validate($dto, VersionTextSourceEnum::DATABASE);
    })->throws(VersionImportException::class, 'is not an instance of ChapterDTO');

    it('throws exception when verse is not an instance of VerseDTO', function () {
        $books = collect([
            new BookDTO(
                'Genesis',
                BookAbbreviationEnum::GEN,
                collect([
                    new ChapterDTO(1, collect([
                        (object) ['number' => 1, 'text' => 'Text']
                    ]))
                ])
            )
        ]);

        $dto = new VersionDTO($books);

        $this->validator->validate($dto, VersionTextSourceEnum::DATABASE);
    })->throws(VersionImportException::class, 'is not an instance of VerseDTO');

    it('validates verse with valid references', function () {
        $references = collect([
            new VerseReferenceDTO('1', 'First reference text'),
            new VerseReferenceDTO('2', 'Second reference text'),
        ]);

        $books = collect([
            new BookDTO(
                'Genesis',
                BookAbbreviationEnum::GEN,
                collect([
                    new ChapterDTO(1, collect([
                        new VerseDTO(1, 'Verse text {{1}} with reference {{2}}', $references)
                    ]))
                ])
            )
        ]);

        $dto = new VersionDTO($books);

        expect(fn() => $this->validator->validate($dto, VersionTextSourceEnum::DATABASE))->not->toThrow(Exception::class);
    });

    it('throws exception when reference is not an instance of VerseReferenceDTO', function () {
        $references = collect([
            (object) ['slug' => '1', 'text' => 'Reference text']
        ]);

        $books = collect([
            new BookDTO(
                'Genesis',
                BookAbbreviationEnum::GEN,
                collect([
                    new ChapterDTO(1, collect([
                        new VerseDTO(1, 'Verse text {{1}}', $references)
                    ]))
                ])
            )
        ]);

        $dto = new VersionDTO($books);

        $this->validator->validate($dto, VersionTextSourceEnum::DATABASE);
    })->throws(VersionImportException::class, 'is not an instance of VerseReferenceDTO');

    it('throws exception when reference slug is empty', function () {
        $references = collect([
            new VerseReferenceDTO('', 'Reference text')
        ]);

        $books = collect([
            new BookDTO(
                'Genesis',
                BookAbbreviationEnum::GEN,
                collect([
                    new ChapterDTO(1, collect([
                        new VerseDTO(1, 'Verse text', $references)
                    ]))
                ])
            )
        ]);

        $dto = new VersionDTO($books);

        $this->validator->validate($dto, VersionTextSourceEnum::DATABASE);
    })->throws(VersionImportException::class, 'has empty slug');

    it('throws exception when reference text is empty', function () {
        $references = collect([
            new VerseReferenceDTO('1', '')
        ]);

        $books = collect([
            new BookDTO(
                'Genesis',
                BookAbbreviationEnum::GEN,
                collect([
                    new ChapterDTO(1, collect([
                        new VerseDTO(1, 'Verse text {{1}}', $references)
                    ]))
                ])
            )
        ]);

        $dto = new VersionDTO($books);

        $this->validator->validate($dto, VersionTextSourceEnum::DATABASE);
    })->throws(VersionImportException::class, 'has empty text');

    it('throws exception when reference slug is missing in verse text', function () {
        $references = collect([
            new VerseReferenceDTO('1', 'Reference text')
        ]);

        $books = collect([
            new BookDTO(
                'Genesis',
                BookAbbreviationEnum::GEN,
                collect([
                    new ChapterDTO(1, collect([
                        new VerseDTO(1, 'Verse text without the slug', $references)
                    ]))
                ])
            )
        ]);

        $dto = new VersionDTO($books);

        $this->validator->validate($dto, VersionTextSourceEnum::DATABASE);
    })->throws(VersionImportException::class, 'is missing its placeholder');

    it('validates multiple references in the same verse', function () {
        $references = collect([
            new VerseReferenceDTO('1', 'First reference'),
            new VerseReferenceDTO('2', 'Second reference'),
            new VerseReferenceDTO('3', 'Third reference'),
        ]);

        $books = collect([
            new BookDTO(
                'Genesis',
                BookAbbreviationEnum::GEN,
                collect([
                    new ChapterDTO(1, collect([
                        new VerseDTO(1, 'Text {{1}} with multiple {{2}} references {{3}}', $references)
                    ]))
                ])
            )
        ]);

        $dto = new VersionDTO($books);

        expect(fn() => $this->validator->validate($dto, VersionTextSourceEnum::DATABASE))->not->toThrow(Exception::class);
    });

    it('throws exception when verse text contains USFM markers', function () {
        $books = collect([
            new BookDTO(
                'Genesis',
                BookAbbreviationEnum::GEN,
                collect([
                    new ChapterDTO(1, collect([
                        new VerseDTO(1, 'Verse text with \f marker')
                    ]))
                ])
            )
        ]);

        $dto = new VersionDTO($books);

        $this->validator->validate($dto, VersionTextSourceEnum::DATABASE);
    })->throws(VersionImportException::class, 'contains USFM markers');

    it('throws exception when verse text contains malformed placeholders', function () {
        $books = collect([
            new BookDTO(
                'Genesis',
                BookAbbreviationEnum::GEN,
                collect([
                    new ChapterDTO(1, collect([
                        new VerseDTO(1, 'Verse text with {invalid} placeholder')
                    ]))
                ])
            )
        ]);

        $dto = new VersionDTO($books);

        $this->validator->validate($dto, VersionTextSourceEnum::DATABASE);
    })->throws(VersionImportException::class, 'contains invalid characters or malformed placeholders');

    it('throws exception when verse text contains unclosed curly braces', function () {
        $books = collect([
            new BookDTO(
                'Genesis',
                BookAbbreviationEnum::GEN,
                collect([
                    new ChapterDTO(1, collect([
                        new VerseDTO(1, 'Verse text with {{unclosed placeholder')
                    ]))
                ])
            )
        ]);

        $dto = new VersionDTO($books);

        $this->validator->validate($dto, VersionTextSourceEnum::DATABASE);
    })->throws(VersionImportException::class, 'contains invalid characters or malformed placeholders');

    it('validates verse text with only text and valid placeholders', function () {
        $references = collect([
            new VerseReferenceDTO('1', 'Reference text')
        ]);

        $books = collect([
            new BookDTO(
                'Genesis',
                BookAbbreviationEnum::GEN,
                collect([
                    new ChapterDTO(1, collect([
                        new VerseDTO(1, 'Clean verse text with {{1}} placeholder', $references)
                    ]))
                ])
            )
        ]);

        $dto = new VersionDTO($books);

        expect(fn() => $this->validator->validate($dto, VersionTextSourceEnum::DATABASE))->not->toThrow(Exception::class);
    });

    it('throws exception when reference text contains USFM markers', function () {
        $references = collect([
            new VerseReferenceDTO('1', 'Reference text with \f marker')
        ]);

        $books = collect([
            new BookDTO(
                'Genesis',
                BookAbbreviationEnum::GEN,
                collect([
                    new ChapterDTO(1, collect([
                        new VerseDTO(1, 'Verse text with {{1}}', $references)
                    ]))
                ])
            )
        ]);

        $dto = new VersionDTO($books);

        $this->validator->validate($dto, VersionTextSourceEnum::DATABASE);
    })->throws(VersionImportException::class, 'contains USFM markers');

    it('throws exception when reference text contains malformed placeholders', function () {
        $references = collect([
            new VerseReferenceDTO('1', 'Reference text with {invalid} placeholder')
        ]);

        $books = collect([
            new BookDTO(
                'Genesis',
                BookAbbreviationEnum::GEN,
                collect([
                    new ChapterDTO(1, collect([
                        new VerseDTO(1, 'Verse text with {{1}}', $references)
                    ]))
                ])
            )
        ]);

        $dto = new VersionDTO($books);

        $this->validator->validate($dto, VersionTextSourceEnum::DATABASE);
    })->throws(VersionImportException::class, 'contains invalid characters or malformed placeholders');

    it('validates reference text with only clean text', function () {
        $references = collect([
            new VerseReferenceDTO('1', 'Clean reference text without markers')
        ]);

        $books = collect([
            new BookDTO(
                'Genesis',
                BookAbbreviationEnum::GEN,
                collect([
                    new ChapterDTO(1, collect([
                        new VerseDTO(1, 'Verse text with {{1}}', $references)
                    ]))
                ])
            )
        ]);

        $dto = new VersionDTO($books);

        expect(fn() => $this->validator->validate($dto, VersionTextSourceEnum::DATABASE))->not->toThrow(Exception::class);
    });

    // External API source validation tests
    it('validates external API source with empty verse text', function () {
        $books = collect([
            new BookDTO(
                'Genesis',
                BookAbbreviationEnum::GEN,
                collect([
                    new ChapterDTO(1, collect([new VerseDTO(1, '')]))
                ])
            )
        ]);

        $dto = new VersionDTO($books);

        expect(fn() => $this->validator->validate($dto, VersionTextSourceEnum::API_BIBLE))->not->toThrow(Exception::class);
    });

    it('throws exception when external API source has non-empty verse text', function () {
        $books = collect([
            new BookDTO(
                'Genesis',
                BookAbbreviationEnum::GEN,
                collect([
                    new ChapterDTO(1, collect([new VerseDTO(1, 'Some text')]))
                ])
            )
        ]);

        $dto = new VersionDTO($books);

        $this->validator->validate($dto, VersionTextSourceEnum::API_BIBLE);
    })->throws(VersionImportException::class, 'must have empty text for external API sources');
});
