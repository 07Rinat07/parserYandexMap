<?php

namespace App\Services\Exports;

use App\Models\Organization;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class OrganizationExportService
{
    public const FORMAT_CSV = 'csv';
    public const FORMAT_JSON = 'json';
    public const FORMAT_TXT = 'txt';

    public function formats(): array
    {
        return [self::FORMAT_CSV, self::FORMAT_JSON, self::FORMAT_TXT];
    }

    public function filename(Organization $organization, string $format): string
    {
        $name = Str::slug($organization->name ?: 'organization-'.$organization->id);
        $name = $name !== '' ? $name : 'organization-'.$organization->id;
        $date = now()->format('Y-m-d_H-i');

        return "{$name}-reviews-{$date}.{$format}";
    }

    public function contentType(string $format): string
    {
        return match ($format) {
            self::FORMAT_CSV => 'text/csv; charset=UTF-8',
            self::FORMAT_JSON => 'application/json; charset=UTF-8',
            self::FORMAT_TXT => 'text/plain; charset=UTF-8',
            default => throw new InvalidArgumentException('Unsupported export format.'),
        };
    }

    public function build(Organization $organization, string $format): string
    {
        $organization->load([
            'reviews' => fn ($query) => $query->latest('review_date')->latest('id'),
            'ratingSnapshots' => fn ($query) => $query->latest('captured_at'),
        ]);

        return match ($format) {
            self::FORMAT_CSV => $this->csv($organization),
            self::FORMAT_JSON => $this->json($organization),
            self::FORMAT_TXT => $this->txt($organization),
            default => throw new InvalidArgumentException('Unsupported export format.'),
        };
    }

    private function csv(Organization $organization): string
    {
        $handle = fopen('php://temp', 'r+');

        fwrite($handle, "\xEF\xBB\xBF");
        $this->putCsv($handle, ['Тип', 'Поле', 'Значение']);
        foreach ($this->summaryRows($organization) as [$field, $value]) {
            $this->putCsv($handle, ['Организация', $field, $value]);
        }

        $this->putCsv($handle, []);
        $this->putCsv($handle, ['Автор', 'Дата', 'Оценка', 'Текст']);
        foreach ($organization->reviews as $review) {
            $this->putCsv($handle, [
                $review->author_name,
                $review->review_date?->toDateString(),
                $review->rating,
                $review->text,
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    private function json(Organization $organization): string
    {
        return json_encode([
            'exported_at' => now()->toIso8601String(),
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'yandex_url' => $organization->yandex_url,
                'normalized_yandex_url' => $organization->normalized_yandex_url,
                'yandex_business_id' => $organization->yandex_business_id,
                'rating' => $organization->rating,
                'ratings_count' => $organization->ratings_count,
                'reviews_count' => $organization->reviews_count,
                'parsing_status' => $organization->parsing_status?->value ?? $organization->parsing_status,
                'parsing_error' => $organization->parsing_error,
                'parser_confidence' => $organization->parser_confidence,
                'parser_metadata' => $organization->parser_metadata,
                'last_parsed_at' => $organization->last_parsed_at?->toIso8601String(),
            ],
            'reviews' => $organization->reviews->map(fn ($review): array => [
                'author_name' => $review->author_name,
                'review_date' => $review->review_date?->toDateString(),
                'rating' => $review->rating,
                'text' => $review->text,
            ])->values(),
            'rating_history' => $organization->ratingSnapshots->map(fn ($snapshot): array => [
                'rating' => $snapshot->rating,
                'ratings_count' => $snapshot->ratings_count,
                'reviews_count' => $snapshot->reviews_count,
                'captured_at' => $snapshot->captured_at?->toIso8601String(),
            ])->values(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private function txt(Organization $organization): string
    {
        $lines = ['Отзывы Яндекс.Карт', ''];

        foreach ($this->summaryRows($organization) as [$field, $value]) {
            $lines[] = "{$field}: {$value}";
        }

        $lines[] = '';
        $lines[] = 'Отзывы:';

        foreach ($organization->reviews as $index => $review) {
            $lines[] = '';
            $lines[] = ($index + 1).'. '.($review->author_name ?: 'Автор не указан');
            $lines[] = 'Дата: '.($review->review_date?->toDateString() ?: '-');
            $lines[] = 'Оценка: '.($review->rating ?? '-');
            $lines[] = 'Текст: '.($review->text ?: '-');
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function summaryRows(Organization $organization): array
    {
        return [
            ['Название', $organization->name ?: '-'],
            ['Ссылка', $organization->normalized_yandex_url],
            ['Рейтинг', $organization->rating ?? '-'],
            ['Количество оценок', $organization->ratings_count ?? '-'],
            ['Количество отзывов', $organization->reviews_count ?? '-'],
            ['Сохранено отзывов', $organization->reviews->count()],
            ['Статус парсинга', $organization->parsing_status?->value ?? $organization->parsing_status],
            ['Parser confidence', $organization->parser_confidence ?? '-'],
            ['Последний парсинг', $organization->last_parsed_at?->toDateTimeString() ?: '-'],
            ['Дата экспорта', now()->toDateTimeString()],
        ];
    }

    private function putCsv($handle, array $row): void
    {
        fputcsv($handle, $row, ';');
    }
}
