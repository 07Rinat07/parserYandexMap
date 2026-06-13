<?php

namespace App\Services\Yandex;

final readonly class YandexMapsUrlNormalizer
{
    public function __construct(private YandexMapsUrlValidator $validator) {}

    public function normalize(string $url): string
    {
        $url = trim($url);
        $this->validator->validate($url);
        $parts = parse_url($url);
        $host = strtolower($parts['host']);
        $path = preg_replace('#/+#', '/', $parts['path'] ?? '/maps/');
        $path = rtrim($path, '/') ?: '/maps';

        $query = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
            $query = array_intersect_key($query, array_flip(['ll', 'z', 'tab']));
            ksort($query);
        }

        $normalized = 'https://'.$host.$path;
        if ($query !== []) {
            $normalized .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $normalized;
    }
}
