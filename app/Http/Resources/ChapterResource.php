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
            'position' => $this->position,
            'book' => new BookResource($this->whenLoaded('book')),
            'version' => new VersionResource($this->whenLoaded('version')),
            'verses' => VerseResource::collection($this->whenLoaded('verses')),
            'previous' => new self($this->whenLoaded('previous')),
            'next' => new self($this->whenLoaded('next')),
        ];
    }
}
