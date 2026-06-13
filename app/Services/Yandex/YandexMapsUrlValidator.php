<?php

namespace App\Services\Yandex;

use App\Exceptions\InvalidYandexMapsUrlException;
use App\Support\SafeUrl;

final readonly class YandexMapsUrlValidator
{
    public function __construct(private SafeUrl $safeUrl) {}

    public function validate(string $url): void
    {
        $parts = $this->safeUrl->assertHttpsUrl($url);
        $host = $parts['host'];
        $path = $parts['path'] ?? '';
        $allowedHosts = config('yandex.allowed_hosts');

        if (! in_array($host, $allowedHosts, true)) {
            throw new InvalidYandexMapsUrlException('Разрешены только ссылки на Яндекс.Карты.');
        }

        if (! str_starts_with($path, '/maps/')) {
            throw new InvalidYandexMapsUrlException('Ссылка должна вести на карточку или страницу Яндекс.Карт.');
        }
    }
}
