<?php

namespace App\Services\Yandex;

use App\DTO\ParsedOrganizationData;
use App\Exceptions\YandexParserTimeoutException;
use App\Exceptions\YandexParserUnavailableException;
use App\Exceptions\YandexParsingException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final readonly class PlaywrightYandexOrganizationParser implements YandexOrganizationParserInterface
{
    public function parse(string $normalizedUrl): ParsedOrganizationData
    {
        $script = base_path('parser/yandex-parser.js');

        if (! is_file($script)) {
            throw new YandexParserUnavailableException('Parser script is not installed.');
        }

        $process = new Process([
            'node',
            $script,
            $normalizedUrl,
        ], base_path(), [
            'YANDEX_MAX_REVIEWS' => (string) config('yandex.max_reviews'),
            'YANDEX_PARSER_TIMEOUT' => (string) config('yandex.timeout'),
        ]);

        $process->setTimeout((int) config('yandex.timeout'));

        try {
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            throw new YandexParserTimeoutException('Yandex parser timed out.', previous: $exception);
        }

        if (! $process->isSuccessful()) {
            throw new YandexParsingException(trim($process->getErrorOutput()) ?: 'Unable to load reviews from Yandex Maps.');
        }

        $payload = json_decode($process->getOutput(), true);

        if (! is_array($payload)) {
            throw new YandexParsingException('Parser returned invalid JSON.');
        }

        if (isset($payload['error'])) {
            throw new YandexParsingException($payload['error']['message'] ?? 'Unable to load reviews from Yandex Maps.');
        }

        return ParsedOrganizationData::fromArray($payload);
    }
}
