<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

class OtelTracer
{
    public function span(string $name, array $attributes, callable $callback): mixed
    {
        $startedAt = hrtime(true);
        $failed = false;

        try {
            return $callback();
        } catch (Throwable $exception) {
            $failed = true;
            $attributes['exception.type'] = $exception::class;
            $attributes['exception.message'] = $exception->getMessage();

            throw $exception;
        } finally {
            $this->export($name, $attributes, $startedAt, hrtime(true), $failed);
        }
    }

    private function export(string $name, array $attributes, int $startedAt, int $endedAt, bool $failed): void
    {
        $endpoint = config('yandex.otel.endpoint');
        if (! $endpoint) {
            return;
        }

        $nowNs = now()->getTimestampMs() * 1_000_000;
        $durationNs = max(0, $endedAt - $startedAt);
        $startNs = $nowNs - $durationNs;

        try {
            Http::timeout(3)->post(rtrim($endpoint, '/').'/v1/traces', [
                'resourceSpans' => [[
                    'resource' => [
                        'attributes' => [[
                            'key' => 'service.name',
                            'value' => ['stringValue' => config('yandex.otel.service_name')],
                        ]],
                    ],
                    'scopeSpans' => [[
                        'scope' => ['name' => 'laravel-parser-job'],
                        'spans' => [[
                            'traceId' => bin2hex(random_bytes(16)),
                            'spanId' => bin2hex(random_bytes(8)),
                            'name' => $name,
                            'kind' => 2,
                            'startTimeUnixNano' => (string) $startNs,
                            'endTimeUnixNano' => (string) $nowNs,
                            'status' => ['code' => $failed ? 2 : 1],
                            'attributes' => collect($attributes)->map(fn (mixed $value, string $key): array => [
                                'key' => $key,
                                'value' => is_bool($value)
                                    ? ['boolValue' => $value]
                                    : (is_int($value) ? ['intValue' => (string) $value] : ['stringValue' => (string) $value]),
                            ])->values()->all(),
                        ]],
                    ]],
                ]],
            ]);
        } catch (Throwable) {
            report('OpenTelemetry trace export failed.');
        }
    }
}
