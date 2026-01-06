<?php

namespace App\Services\Version\Validators;

use App\Models\Version;
use App\Services\Version\DTOs\VersionDTO;
use App\Exceptions\Version\VersionImportException;

class VersionValidator
{
    public function validateBeforeImport(VersionDTO $dto): void
    {
        $booksCount = $dto->books->count();
        
        if ($booksCount !== 66) {
            throw new VersionImportException('invalid_books_count', "Expected 66 books but got {$booksCount}");
        }

        foreach ($dto->books as $book) {
            if ($book->chapters->isEmpty()) {
                throw new VersionImportException('missing_chapters', "Book '{$book->name}' is missing chapters");
            }

            foreach ($book->chapters as $chapter) {
                if ($chapter->verses->isEmpty()) {
                    throw new VersionImportException('missing_verses', "Chapter {$chapter->number} in book '{$book->name}' is missing verses");
                }

                foreach ($chapter->verses as $verse) {
                    if (empty(trim($verse->text))) {
                        throw new VersionImportException('empty_verse', "Verse {$verse->number} in chapter {$chapter->number} of book '{$book->name}' has empty text");
                    }
                }
            }
        }
    }

    public function validateAfterImport(Version $version): void
    {
        // Count validation removed - will be implemented later if needed
    }
}
