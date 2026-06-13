<?php

namespace App\Services\Exports;

use App\Models\Organization;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

final class OrganizationExportService
{
    public const FORMAT_CSV = 'csv';
    public const FORMAT_JSON = 'json';
    public const FORMAT_TXT = 'txt';
    public const FORMAT_XLSX = 'xlsx';

    public function formats(): array
    {
        return [self::FORMAT_XLSX, self::FORMAT_CSV, self::FORMAT_JSON, self::FORMAT_TXT];
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
            self::FORMAT_XLSX => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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
            self::FORMAT_XLSX => $this->xlsx($organization),
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

    private function xlsx(Organization $organization): string
    {
        $path = tempnam(sys_get_temp_dir(), 'yandex-reviews-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary XLSX file.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to open temporary XLSX archive.');
        }

        $zip->addFromString('[Content_Types].xml', $this->xlsxContentTypes());
        $zip->addFromString('_rels/.rels', $this->xlsxRootRels());
        $zip->addFromString('xl/workbook.xml', $this->xlsxWorkbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xlsxWorkbookRels());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->xlsxSheet(array_merge(
            [['Поле', 'Значение']],
            $this->summaryRows($organization),
        )));
        $zip->addFromString('xl/worksheets/sheet2.xml', $this->xlsxSheet($this->reviewRows($organization)));
        $zip->addFromString('xl/worksheets/sheet3.xml', $this->xlsxSheet($this->historyRows($organization)));
        $zip->close();

        $content = file_get_contents($path);
        @unlink($path);

        if ($content === false) {
            throw new RuntimeException('Unable to read temporary XLSX file.');
        }

        return $content;
    }

    private function reviewRows(Organization $organization): array
    {
        $rows = [['Автор', 'Дата', 'Оценка', 'Текст']];

        foreach ($organization->reviews as $review) {
            $rows[] = [
                $review->author_name,
                $review->review_date?->toDateString(),
                $review->rating,
                $review->text,
            ];
        }

        return $rows;
    }

    private function historyRows(Organization $organization): array
    {
        $rows = [['Рейтинг', 'Количество оценок', 'Количество отзывов', 'Дата снимка']];

        foreach ($organization->ratingSnapshots as $snapshot) {
            $rows[] = [
                $snapshot->rating,
                $snapshot->ratings_count,
                $snapshot->reviews_count,
                $snapshot->captured_at?->toDateTimeString(),
            ];
        }

        return $rows;
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

    private function xlsxContentTypes(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML;
    }

    private function xlsxRootRels(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
    }

    private function xlsxWorkbook(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Организация" sheetId="1" r:id="rId1"/>
    <sheet name="Отзывы" sheetId="2" r:id="rId2"/>
    <sheet name="История" sheetId="3" r:id="rId3"/>
  </sheets>
</workbook>
XML;
    }

    private function xlsxWorkbookRels(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>
</Relationships>
XML;
    }

    private function xlsxSheet(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $number = $rowIndex + 1;
            $xml .= '<row r="'.$number.'">';

            foreach (array_values($row) as $columnIndex => $value) {
                $reference = $this->xlsxColumn($columnIndex + 1).$number;
                $xml .= $this->xlsxCell($reference, $value);
            }

            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private function xlsxCell(string $reference, mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            return '<c r="'.$reference.'"><v>'.$value.'</v></c>';
        }

        $text = htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_COMPAT, 'UTF-8');

        return '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>';
    }

    private function xlsxColumn(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }
}
