<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChapterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'verses_count' => $this->whenCounted('verses'),
            'book' => new BookResource($this->whenLoaded('book')),
            'verses' => VerseResource::collection($this->whenLoaded('verses')),
        ];
    }
}
