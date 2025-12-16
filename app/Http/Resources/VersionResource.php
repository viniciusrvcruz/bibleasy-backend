<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'language' => $this->language,
            'copyright' => $this->copyright,
            'chapters_count' => $this->whenCounted('chapters'),
            'verses_count' => $this->whenCounted('verses'),
        ];
    }
}
