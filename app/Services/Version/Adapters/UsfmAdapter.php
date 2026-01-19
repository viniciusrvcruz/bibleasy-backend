<?php

namespace App\Services\Version\Adapters;

use App\Enums\BookAbbreviationEnum;
use App\Services\Version\DTOs\FileDTO;
use App\Services\Version\DTOs\VersionDTO;
use App\Services\Version\Interfaces\VersionAdapterInterface;
use App\Services\Version\Adapters\Usfm\UsfmBookParser;
use App\Services\Version\Adapters\Usfm\UsfmLineParser;
use App\Exceptions\Version\VersionImportException;

/**
 * Adapter for USFM (Unified Standard Format Markers) format
 * Each file represents one book of the Bible
 */
class UsfmAdapter implements VersionAdapterInterface
{
    public function __construct(
        private readonly UsfmBookParser $bookParser,
        private readonly UsfmLineParser $lineParser
    ) {}

    /**
     * @param array<int, FileDTO> $files
     */
    public function adapt(array $files): VersionDTO
    {
        $books = collect($files)->map(function ($file) {
            $this->validateFile($file);

            $bookAbbreviation = $this->getBookAbbreviation($file->content, $file->fileName);

            return $this->bookParser->parse($file->content, $bookAbbreviation);
        });

        return new VersionDTO($books);
    }

    /**
     * Validate file extension and name
     */
    private function validateFile(FileDTO $file): void
    {
        if (strtolower($file->extension) !== 'usfm') {
            throw new VersionImportException(
                'invalid_file_extension',
                'File must have .usfm extension'
            );
        }
    }

    /**
     * Extract book abbreviation from first line in USFM content
     */
    private function getBookAbbreviation(string $content, string $fileName): BookAbbreviationEnum
    {
        $lines = explode("\n", $content);
        $firstLine = $lines[0] ?? '';

        $abbreviation = $this->lineParser->parseBookAbbreviation($firstLine);

        if ($abbreviation === null) {
            throw new VersionImportException(
                'missing_id_marker',
                '\\id marker not found in USFM file ' . $fileName
            );
        }

        $normalized = strtolower($abbreviation);

        return BookAbbreviationEnum::tryFrom($normalized) ?? throw new VersionImportException(
            'invalid_book_abbreviation',
            "Book abbreviation '{$abbreviation}' from \\id marker does not match any book abbreviation from BookAbbreviationEnum in file {$fileName}"
        );
    }
}
