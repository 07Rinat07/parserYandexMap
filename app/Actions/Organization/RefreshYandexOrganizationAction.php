<?php

namespace App\Actions\Organization;

use App\Enums\ParsingStatus;
use App\Jobs\ParseYandexOrganizationJob;
use App\Models\Organization;

final class RefreshYandexOrganizationAction
{
    public function execute(Organization $organization): Organization
    {
        if (in_array($organization->parsing_status, [ParsingStatus::Pending, ParsingStatus::Processing], true)) {
            return $organization->refresh();
        }

        $organization->update([
            'parsing_status' => ParsingStatus::Pending,
            'parsing_error' => null,
            'parser_metadata' => array_replace_recursive($organization->parser_metadata ?? [], [
                'progress' => [
                    'stage' => 'queued',
                    'message' => 'Задача поставлена в очередь.',
                    'reviews_seen' => 0,
                    'updated_at' => now()->toIso8601String(),
                ],
            ]),
        ]);

        ParseYandexOrganizationJob::dispatch($organization->id);

        return $organization->refresh();
    }
}
