<?php

namespace App\Http\Resources;

use App\Services\Chapter\DTOs\VerseReferenceResponseDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VerseReferenceResponseDTO
 */
class VerseReferenceResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'text' => $this->text,
        ];
    }
}
