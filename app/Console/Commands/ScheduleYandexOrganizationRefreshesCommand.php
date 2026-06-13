<?php

namespace App\Console\Commands;

use App\Enums\ParsingStatus;
use App\Jobs\ParseYandexOrganizationJob;
use App\Models\Organization;
use Illuminate\Console\Command;

class ScheduleYandexOrganizationRefreshesCommand extends Command
{
    protected $signature = 'yandex:refresh-organizations {--limit=100}';

    protected $description = 'Dispatch scheduled refresh jobs for saved Yandex Maps organizations.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $organizations = Organization::query()
            ->whereNotIn('parsing_status', [ParsingStatus::Pending, ParsingStatus::Processing])
            ->oldest('last_parsed_at')
            ->limit($limit)
            ->get();

        foreach ($organizations as $organization) {
            $organization->update([
                'parsing_status' => ParsingStatus::Pending,
                'parsing_error' => null,
            ]);

            ParseYandexOrganizationJob::dispatch($organization->id);
        }

        $this->info("Dispatched {$organizations->count()} organization refresh jobs.");

        return self::SUCCESS;
    }
}
