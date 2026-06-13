<?php

namespace App\Actions\Organization;

use App\Enums\ParsingStatus;
use App\Jobs\ParseYandexOrganizationJob;
use App\Models\Organization;
use App\Models\User;
use App\Services\Yandex\YandexMapsUrlNormalizer;

final readonly class SaveYandexOrganizationAction
{
    public function __construct(private YandexMapsUrlNormalizer $normalizer) {}

    public function execute(User $user, string $url): Organization
    {
        $normalized = $this->normalizer->normalize($url);

        $organization = Organization::query()
            ->whereBelongsTo($user)
            ->where('normalized_yandex_url', $normalized)
            ->first();

        if ($organization && in_array($organization->parsing_status, [ParsingStatus::Pending, ParsingStatus::Processing], true)) {
            return $organization->refresh();
        }

        $organization = Organization::query()->updateOrCreate(
            ['user_id' => $user->id, 'normalized_yandex_url' => $normalized],
            [
                'yandex_url' => trim($url),
                'parsing_status' => ParsingStatus::Pending,
                'parsing_error' => null,
                'parser_metadata' => [
                    'progress' => [
                        'stage' => 'queued',
                        'message' => 'Задача поставлена в очередь.',
                        'reviews_seen' => 0,
                        'updated_at' => now()->toIso8601String(),
                    ],
                ],
            ],
        );

        ParseYandexOrganizationJob::dispatch($organization->id);

        return $organization->refresh();
    }
}
