<?php

namespace App\Http\Resources;

use App\Services\Chapter\DTOs\ChapterResponseDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChapterResponseDTO
 */
class ChapterResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'book' => [
                'name' => $this->bookName,
                'abbreviation' => $this->bookAbbreviation->value,
            ],
            'verses' => VerseResponseResource::collection($this->verses),
        ];
    }
}
