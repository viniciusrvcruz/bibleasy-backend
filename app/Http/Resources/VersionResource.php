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
            'abbreviation' => $this->abbreviation,
            'name' => $this->name,
            'language' => $this->language,
            'copyright' => $this->copyright,
        ];
    }
}
