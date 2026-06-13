<?php

namespace App\Actions\Organization;

use App\Enums\ParsingStatus;
use App\Jobs\ParseYandexOrganizationJob;
use App\Models\Organization;

final class RefreshYandexOrganizationAction
{
    public function execute(Organization $organization): Organization
    {
        $organization->update([
            'parsing_status' => ParsingStatus::Pending,
            'parsing_error' => null,
        ]);

        ParseYandexOrganizationJob::dispatch($organization->id);

        return $organization->refresh();
    }
}
