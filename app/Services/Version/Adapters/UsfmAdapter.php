<?php

namespace App\Services\Version\Adapters;

use App\Enums\BookAbbreviationEnum;
use App\Services\Version\DTOs\VersionDTO;
use App\Services\Version\Interfaces\VersionAdapterInterface;
use App\Services\Version\Adapters\Usfm\UsfmBookParser;
use App\Exceptions\Version\VersionImportException;

/**
 * Adapter for USFM (Unified Standard Format Markers) format
 * Each file represents one book of the Bible
 */
class UsfmAdapter implements VersionAdapterInterface
{
    public function __construct(
        private readonly UsfmBookParser $bookParser
    ) {}

    public function adapt(array $files): VersionDTO
    {
        $books = collect($files)->map(function ($file) {
            $this->validateFile($file);

            $bookAbbreviation = $this->getBookAbbreviationFromFileName($file->fileName);

            return $this->bookParser->parse($file->content, $bookAbbreviation);
        });

        return new VersionDTO($books);
    }

    /**
     * Validate file extension and name
     */
    private function validateFile($file): void
    {
        if (strtolower($file->extension) !== 'usfm') {
            throw new VersionImportException(
                'invalid_file_extension',
                'File must have .usfm extension'
            );
        }
    }

    /**
     * Extract book abbreviation from file name
     * File name should be like "mat.usfm" or "MAT.usfm"
     */
    private function getBookAbbreviationFromFileName(string $fileName): BookAbbreviationEnum
    {
        $nameWithoutExtension = pathinfo($fileName, PATHINFO_FILENAME);
        $normalized = strtolower($nameWithoutExtension);

        return BookAbbreviationEnum::tryFrom($normalized) ?? throw new VersionImportException(
            'invalid_file_name',
            "File name '{$fileName}' does not match any book abbreviation from BookAbbreviationEnum"
        );
    }
}
