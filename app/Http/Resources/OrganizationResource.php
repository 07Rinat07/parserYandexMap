<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'yandex_url' => $this->yandex_url,
            'normalized_yandex_url' => $this->normalized_yandex_url,
            'name' => $this->name,
            'rating' => $this->rating,
            'ratings_count' => $this->ratings_count,
            'reviews_count' => $this->reviews_count,
            'parsing_status' => $this->parsing_status?->value ?? $this->parsing_status,
            'parsing_error' => $this->parsing_error,
            'last_parsed_at' => $this->last_parsed_at?->toIso8601String(),
        ];
    }
}
