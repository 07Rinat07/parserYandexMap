<?php

namespace App\Services\Yandex;

use App\DTO\ParsedOrganizationData;

final class FakeYandexOrganizationParser implements YandexOrganizationParserInterface
{
    public function parse(string $normalizedUrl): ParsedOrganizationData
    {
        return ParsedOrganizationData::fromArray([
            'name' => 'Тестовая организация',
            'rating' => 4.7,
            'ratings_count' => 1284,
            'reviews_count' => 3,
            'yandex_business_id' => hash('crc32b', $normalizedUrl),
            'reviews' => [
                [
                    'external_id' => 'fake-1',
                    'author_name' => 'Иван Иванов',
                    'review_date' => '2026-01-10',
                    'text' => 'Отличный сервис, быстро помогли и подробно ответили на вопросы.',
                    'rating' => 5,
                    'raw_payload' => ['source' => 'fake'],
                ],
                [
                    'external_id' => 'fake-2',
                    'author_name' => 'Анна Смирнова',
                    'review_date' => '2025-12-18',
                    'text' => 'Хорошее место. Есть небольшие задержки, но результат понравился.',
                    'rating' => 4,
                    'raw_payload' => ['source' => 'fake'],
                ],
                [
                    'external_id' => 'fake-3',
                    'author_name' => 'Павел Орлов',
                    'review_date' => '2025-10-03',
                    'text' => 'Все прошло спокойно и понятно.',
                    'rating' => 5,
                    'raw_payload' => ['source' => 'fake'],
                ],
            ],
        ]);
    }
}
