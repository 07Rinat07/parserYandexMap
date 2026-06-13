<?php

namespace App\Services\Yandex;

use App\DTO\ParsedOrganizationData;
use App\Exceptions\YandexParserTimeoutException;
use App\Exceptions\YandexParserUnavailableException;
use App\Exceptions\YandexParsingException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

final class ParserMicroserviceYandexOrganizationParser implements YandexOrganizationParserInterface
{
    public function parse(string $normalizedUrl): ParsedOrganizationData
    {
        try {
            $response = Http::timeout((int) config('yandex.timeout'))
                ->acceptJson()
                ->post(config('yandex.parser_service_url').'/parse', [
                    'url' => $normalizedUrl,
                    'max_reviews' => (int) config('yandex.max_reviews'),
                    'timeout' => (int) config('yandex.timeout'),
                ])
                ->throw();
        } catch (ConnectionException $exception) {
            throw new YandexParserUnavailableException('Parser microservice is unavailable.', previous: $exception);
        } catch (RequestException $exception) {
            if ($exception->response->status() === 408) {
                throw new YandexParserTimeoutException('Parser microservice timed out.', previous: $exception);
            }

            throw new YandexParsingException($exception->response->json('error.message') ?? 'Parser microservice failed.', previous: $exception);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new YandexParsingException('Parser microservice returned invalid JSON.');
        }

        if (isset($payload['error'])) {
            throw new YandexParsingException($payload['error']['message'] ?? 'Unable to load reviews from Yandex Maps.');
        }

        return ParsedOrganizationData::fromArray($payload);
    }
}
