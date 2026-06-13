<?php

namespace App\DTO;

use Illuminate\Support\Collection;

final readonly class ParsedOrganizationData
{
    /**
     * @param  Collection<int, ParsedReviewData>  $reviews
     */
    public function __construct(
        public ?string $name,
        public ?float $rating,
        public ?int $ratingsCount,
        public ?int $reviewsCount,
        public Collection $reviews,
        public ?string $yandexBusinessId = null,
        public ?int $parserConfidence = null,
        public ?array $parserMetadata = null,
    ) {}

    public static function fromArray(array $payload): self
    {
        $reviews = collect($payload['reviews'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): ParsedReviewData => ParsedReviewData::fromArray($item))
            ->values();

        return new self(
            name: self::nullableString($payload['name'] ?? null),
            rating: isset($payload['rating']) ? round((float) $payload['rating'], 2) : null,
            ratingsCount: isset($payload['ratings_count']) ? max(0, (int) $payload['ratings_count']) : null,
            reviewsCount: isset($payload['reviews_count']) ? max(0, (int) $payload['reviews_count']) : $reviews->count(),
            reviews: $reviews,
            yandexBusinessId: self::nullableString($payload['yandex_business_id'] ?? null),
            parserConfidence: isset($payload['parser']['confidence']) ? max(0, min(100, (int) $payload['parser']['confidence'])) : null,
            parserMetadata: isset($payload['parser']) && is_array($payload['parser']) ? $payload['parser'] : null,
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
