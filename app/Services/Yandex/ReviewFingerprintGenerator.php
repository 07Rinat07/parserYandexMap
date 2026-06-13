<?php

namespace App\Services\Yandex;

use App\DTO\ParsedReviewData;

final class ReviewFingerprintGenerator
{
    public function generate(ParsedReviewData $review): string
    {
        return hash('sha256', implode('|', [
            mb_strtolower(trim((string) $review->authorName)),
            optional($review->reviewDate)->toDateString(),
            (string) $review->rating,
            preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $review->text))),
        ]));
    }
}
