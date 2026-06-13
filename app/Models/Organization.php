<?php

namespace App\Models;

use App\Enums\ParsingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo as BelongsToRelation;
use Illuminate\Database\Eloquent\Relations\HasMany as HasManyRelation;

#[Fillable([
    'user_id',
    'yandex_url',
    'normalized_yandex_url',
    'yandex_business_id',
    'name',
    'rating',
    'ratings_count',
    'reviews_count',
    'parsing_status',
    'parsing_error',
    'parser_confidence',
    'parser_metadata',
    'last_parsed_at',
])]
class Organization extends Model
{
    protected function casts(): array
    {
        return [
            'rating' => 'float',
            'ratings_count' => 'integer',
            'reviews_count' => 'integer',
            'parsing_status' => ParsingStatus::class,
            'parser_confidence' => 'integer',
            'parser_metadata' => 'array',
            'last_parsed_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsToRelation
    {
        return $this->belongsTo(User::class);
    }

    public function reviews(): HasManyRelation
    {
        return $this->hasMany(Review::class);
    }

    public function ratingSnapshots(): HasManyRelation
    {
        return $this->hasMany(RatingSnapshot::class);
    }
}
