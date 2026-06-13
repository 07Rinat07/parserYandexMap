<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParserErrorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'organization_id' => $this->id,
            'organization_name' => $this->name,
            'normalized_yandex_url' => $this->normalized_yandex_url,
            'parsing_error' => $this->parsing_error,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
