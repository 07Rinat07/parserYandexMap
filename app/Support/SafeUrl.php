<?php

namespace App\Support;

use App\Exceptions\InvalidYandexMapsUrlException;

final class SafeUrl
{
    public function assertHttpsUrl(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new InvalidYandexMapsUrlException('Введите корректную ссылку Яндекс.Карт.');
        }

        if (strtolower($parts['scheme']) !== 'https') {
            throw new InvalidYandexMapsUrlException('Разрешены только HTTPS-ссылки.');
        }

        $host = strtolower(idn_to_ascii($parts['host']) ?: $parts['host']);

        if ($this->isUnsafeHost($host)) {
            throw new InvalidYandexMapsUrlException('Этот адрес не может быть использован.');
        }

        $parts['host'] = $host;

        return $parts;
    }

    public function isUnsafeHost(string $host): bool
    {
        $host = trim(strtolower($host), '.');

        if (in_array($host, ['localhost', '0.0.0.0'], true)) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return false;
    }
}
