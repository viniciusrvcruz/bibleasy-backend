<?php

namespace App\Services\Chapter\Adapters;

use App\Enums\BookAbbreviationEnum;
use App\Exceptions\Chapter\ChapterSourceException;
use App\Models\Chapter;
use App\Models\Version;
use App\Services\Chapter\DTOs\ChapterResponseDTO;
use App\Services\Chapter\Interfaces\ChapterSourceAdapterInterface;
use App\Services\Chapter\Parsers\ApiBibleContentParser;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fetches chapter content from api.bible and maps to ChapterResponseDTO.
 * Validates chapter exists in DB before making external requests.
 */
class ApiBibleChapterAdapter implements ChapterSourceAdapterInterface
{
    public function __construct(
        private readonly ApiBibleContentParser $parser
    ) {}

    public function getChapter(
        Version $version,
        BookAbbreviationEnum $abbreviation,
        int $number
    ): ChapterResponseDTO {
        $this->validateChapterExists($version, $abbreviation, $number);

        $externalId = $version->external_version_id;
        if (empty($externalId)) {
            throw new ChapterSourceException('invalid_response', 'Version has no external_version_id for api.bible.');
        }

        $baseUrl = rtrim(config('services.api_bible.base_url', ''), '/');
        $key = config('services.api_bible.key', '');
        if ($baseUrl === '' || $key === '') {
            throw new ChapterSourceException('external_api_error', 'API Bible is not configured.');
        }

        $bookId = strtoupper($abbreviation->value);
        $url = "{$baseUrl}/bibles/{$externalId}/chapters/{$bookId}.{$number}";

        try {
            $response = Http::withHeaders(['api-key' => $key])
                ->timeout(15)
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

            $content = $data['data']['content'];
            $bookIdFromApi = $data['data']['bookId'] ?? $bookId;
            $chapterNumber = (string) ($data['data']['number'] ?? $number);

            $verses = $this->parser->parse($content, $bookIdFromApi, $chapterNumber);

            $book = $version->books()->where('abbreviation', $abbreviation)->first();
            $bookName = $book?->name ?? $abbreviation->value;

            return new ChapterResponseDTO(
                number: $number,
                bookName: $bookName,
                bookAbbreviation: $abbreviation,
                verses: $verses
            );
        } catch (ChapterSourceException $e) {
            throw $e;
        } catch (RequestException $e) {
            throw new ChapterSourceException(
                'external_api_error',
                'API Bible request failed: ' . $e->getMessage()
            );
        }
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
