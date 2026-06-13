<?php

namespace App\Actions\Organization;

use App\DTO\ParsedOrganizationData;
use App\Enums\ParsingStatus;
use App\Models\Organization;
use App\Services\Yandex\ReviewFingerprintGenerator;
use Illuminate\Support\Facades\DB;

final readonly class PersistParsedOrganizationAction
{
    public function __construct(private ReviewFingerprintGenerator $fingerprints) {}

    public function execute(Organization $organization, ParsedOrganizationData $data): Organization
    {
        return DB::transaction(function () use ($organization, $data): Organization {
            $organization->update([
                'yandex_business_id' => $data->yandexBusinessId,
                'name' => $data->name,
                'rating' => $data->rating,
                'ratings_count' => $data->ratingsCount,
                'reviews_count' => $data->reviewsCount,
                'parsing_status' => ParsingStatus::Success,
                'parsing_error' => null,
                'last_parsed_at' => now(),
            ]);

            foreach ($data->reviews as $review) {
                $fingerprint = $this->fingerprints->generate($review);
                $attributes = $review->externalId
                    ? ['organization_id' => $organization->id, 'external_id' => $review->externalId]
                    : ['organization_id' => $organization->id, 'fingerprint' => $fingerprint];

                $organization->reviews()->updateOrCreate($attributes, [
                    'fingerprint' => $fingerprint,
                    'author_name' => $review->authorName,
                    'review_date' => $review->reviewDate?->toDateString(),
                    'text' => $review->text,
                    'rating' => $review->rating,
                    'raw_payload' => $review->rawPayload,
                ]);
            }

            return $organization->refresh();
        });
    }
}
