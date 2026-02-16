<?php

namespace App\Http\Resources;

use App\Services\Chapter\DTOs\VerseResponseDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VerseResponseDTO
 */
class VerseResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'text' => $this->text,
            'titles' => VerseTitleResource::collection($this->titles),
            'references' => VerseReferenceResponseResource::collection($this->references),
        ];
    }
}
