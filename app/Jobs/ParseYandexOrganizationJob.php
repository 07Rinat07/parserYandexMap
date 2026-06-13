<?php

namespace App\Jobs;

use App\Actions\Organization\PersistParsedOrganizationAction;
use App\Enums\ParsingStatus;
use App\Models\Organization;
use App\Services\Yandex\YandexOrganizationParserInterface;
use App\Support\OtelTracer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ParseYandexOrganizationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 190;

    public function __construct(public int $organizationId)
    {
        $this->timeout = (int) config('yandex.timeout') + 10;
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(
        YandexOrganizationParserInterface $parser,
        PersistParsedOrganizationAction $persist,
        ?OtelTracer $tracer = null,
    ): void {
        $organization = Organization::query()->findOrFail($this->organizationId);
        $tracer ??= app(OtelTracer::class);

        $tracer->span('ParseYandexOrganizationJob', [
            'organization.id' => $organization->id,
            'organization.url_host' => parse_url($organization->normalized_yandex_url, PHP_URL_HOST) ?: 'unknown',
            'queue.attempt' => $this->attempts(),
        ], function () use ($organization, $parser, $persist): void {
            $organization->update([
                'parsing_status' => ParsingStatus::Processing,
                'parsing_error' => null,
            ]);

            try {
                $data = $parser->parse($organization->normalized_yandex_url);
                $persist->execute($organization, $data);
            } catch (Throwable $exception) {
                if ($this->job && $this->attempts() < $this->tries) {
                    throw $exception;
                }

                $this->markAsFailed($organization, $exception);
            }
        });
    }

    private function markAsFailed(Organization $organization, Throwable $exception): void
    {
        Log::warning('Yandex organization parsing failed.', [
            'organization_id' => $organization->id,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        $organization->update([
            'parsing_status' => ParsingStatus::Failed,
            'parsing_error' => mb_substr($exception->getMessage() ?: 'Не удалось получить данные Яндекс.Карт.', 0, 500),
        ]);
    }
}
