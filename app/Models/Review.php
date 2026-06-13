<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo as BelongsToRelation;

#[Fillable([
    'organization_id',
    'external_id',
    'fingerprint',
    'author_name',
    'review_date',
    'text',
    'rating',
    'raw_payload',
])]
class Review extends Model
{
    protected function casts(): array
    {
        return [
            'review_date' => 'date',
            'rating' => 'integer',
            'raw_payload' => 'array',
        ];
    }

    public function organization(): BelongsToRelation
    {
        return $this->belongsTo(Organization::class);
    }
}
