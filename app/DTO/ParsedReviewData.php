<?php

namespace App\DTO;

use Carbon\CarbonImmutable;

final readonly class ParsedReviewData
{
    public function __construct(
        public ?string $externalId,
        public ?string $authorName,
        public ?CarbonImmutable $reviewDate,
        public ?string $text,
        public ?int $rating,
        public ?array $rawPayload = null,
    ) {}

    public static function fromArray(array $payload): self
    {
        $date = $payload['review_date'] ?? null;

        return new self(
            externalId: self::nullableString($payload['external_id'] ?? null),
            authorName: self::nullableString($payload['author_name'] ?? null),
            reviewDate: $date ? CarbonImmutable::parse($date)->startOfDay() : null,
            text: self::nullableString($payload['text'] ?? null),
            rating: isset($payload['rating']) ? max(1, min(5, (int) $payload['rating'])) : null,
            rawPayload: isset($payload['raw_payload']) && is_array($payload['raw_payload']) ? $payload['raw_payload'] : null,
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
