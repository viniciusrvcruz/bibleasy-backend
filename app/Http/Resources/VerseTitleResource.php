<?php

namespace App\Http\Resources;

use App\Services\Chapter\DTOs\VerseTitleDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VerseTitleDTO
 */
class VerseTitleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'text' => $this->text,
            'type' => $this->type->value,
        ];
    }
}
