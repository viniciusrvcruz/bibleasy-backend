<?php

namespace App\Services\Chapter\Validators;

use App\Services\Chapter\DTOs\VerseResponseDTO;
use Illuminate\Support\Collection;

/**
 * Validates that every verse reference has its slug present as {{slug}} in the verse text or in any title text.
 * Returns an array of warnings for each reference whose slug is missing from both.
 */
class ReferenceSlugValidator
{
    private const LOG_TRUNCATE_LENGTH = 50;

    /**
     * Returns warnings for references whose slug is not found in verse or title text.
     *
     * @param  Collection<int, VerseResponseDTO>  $verses
     * @return array<int, array{message: string, context: array<string, mixed>}>
     */
    public function getWarnings(Collection $verses, string $bookId, string $chapterNumber): array
    {
        $warnings = [];
        $contextKey = "{$bookId}.{$chapterNumber}";

        foreach ($verses as $verse) {
            if ($verse->references->isEmpty()) {
                continue;
            }

            $verseText = $verse->text;
            $titleTexts = $verse->titles->map(fn ($t) => $t->text)->implode(' ');
            $combinedText = $verseText . ' ' . $titleTexts;

            foreach ($verse->references as $reference) {
                $placeholder = '{{' . $reference->slug . '}}';
                if (! str_contains($combinedText, $placeholder)) {
                    $warnings[] = [
                        'message' => 'ApiBibleContentParser: reference slug missing from verse and title text.',
                        'context' => [
                            'context' => $contextKey,
                            'verse_number' => $verse->number,
                            'reference_slug' => $reference->slug,
                            'reference_text_snippet' => $this->truncate($reference->text),
                        ],
                    ];
                }
            }
        }

        return $warnings;
    }

    private function truncate(string $text, int $maxLength = self::LOG_TRUNCATE_LENGTH): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength) . '…';
    }
}
