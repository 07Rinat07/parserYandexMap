<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'rating',
    'ratings_count',
    'reviews_count',
    'captured_at',
])]
class RatingSnapshot extends Model
{
    protected function casts(): array
    {
        return [
            'rating' => 'float',
            'ratings_count' => 'integer',
            'reviews_count' => 'integer',
            'captured_at' => 'immutable_datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
