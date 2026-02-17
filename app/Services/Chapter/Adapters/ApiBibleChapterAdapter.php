<?php

namespace App\Services\Chapter\Adapters;

use App\Enums\BookAbbreviationEnum;
use App\Exceptions\Chapter\ChapterSourceException;
use App\Models\Chapter;
use App\Models\Version;
use App\Services\Chapter\DTOs\ChapterResponseDTO;
use App\Services\Chapter\Parsers\ApiBibleContentParser;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fetches chapter content from api.bible and maps to ChapterResponseDTO.
 * Raw API response is cached; parsing and DTO building run on every request.
 * Validates chapter exists in DB before making external requests.
 */
class ApiBibleChapterAdapter extends AbstractCachedChapterAdapter
{
    public function __construct(
        private readonly ApiBibleContentParser $parser
    ) {}

    protected function fetchRawChapter(
        Version $version,
        BookAbbreviationEnum $abbreviation,
        int $number
    ): array {
        $this->validateChapterExists($version, $abbreviation, $number);

        $externalId = $version->external_version_id;

        $baseUrl = rtrim(config('services.api_bible.base_url'), '/');
        $key = config('services.api_bible.key');

        if (empty($baseUrl) || empty($key)) {
            throw new ChapterSourceException('external_api_error', 'API Bible is not configured.');
        }

        $bookId = strtoupper($abbreviation->value);
        $url = "{$baseUrl}/bibles/{$externalId}/chapters/{$bookId}.{$number}";

        $response = Http::withHeaders(['api-key' => $key])
            ->timeout(5)
            ->get($url, [
                'content-type' => 'json',
                'include-notes' => 'true',
                'include-titles' => 'true'
            ]);

        if (! $response->successful()) {
            throw new ChapterSourceException(
                'external_api_error',
                'API Bible request failed: ' . $response->status()
            );
        }

        $data = $response->json();
        if (! is_array($data) || ! isset($data['data']['content']) || ! is_array($data['data']['content'])) {
            throw new ChapterSourceException('invalid_response', 'Invalid API Bible response structure.');
        }

        return $data;
    }

    protected function processRawToDto(
        array $raw,
        Version $version,
        BookAbbreviationEnum $abbreviation,
        int $number
    ): ChapterResponseDTO {
        $content = $raw['data']['content'];
        $bookId = $raw['data']['bookId'] ?? strtoupper($abbreviation->value);
        $chapterNumber = (string) ($raw['data']['number'] ?? $number);

        $verses = $this->parser->parse($content, $bookId, $chapterNumber);

        $book = $version->books()->where('abbreviation', $abbreviation)->first();
        $bookName = $book?->name ?? $abbreviation->value;

        return new ChapterResponseDTO(
            number: $number,
            bookName: $bookName,
            bookAbbreviation: $abbreviation,
            verses: $verses
        );
    }

    /**
     * Ensure chapter exists in DB before calling external API (rate limit protection).
     *
     * @throws ChapterSourceException
     */
    private function validateChapterExists(Version $version, BookAbbreviationEnum $abbreviation, int $number): void
    {
        $exists = Chapter::where('number', $number)
            ->whereHas('book', fn (Builder $query) => $query
                ->where('abbreviation', $abbreviation)
                ->where('version_id', $version->id))
            ->exists();

        if (! $exists) {
            throw new ChapterSourceException(
                'chapter_not_found',
                "Chapter {$number} not found for the given book and version."
            );
        }
    }
}
