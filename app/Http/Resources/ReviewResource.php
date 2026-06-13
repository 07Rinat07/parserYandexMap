<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author_name' => $this->author_name,
            'review_date' => $this->review_date?->toDateString(),
            'text' => $this->text,
            'rating' => $this->rating,
        ];
    }
}
