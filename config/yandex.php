<?php

return [
    'max_reviews' => (int) env('YANDEX_MAX_REVIEWS', 700),
    'timeout' => (int) env('YANDEX_PARSER_TIMEOUT', 180),
    'minimum_parser_confidence' => (int) env('YANDEX_MINIMUM_PARSER_CONFIDENCE', 30),
    'parser_mode' => env('YANDEX_PARSER_MODE', 'playwright'),
    'parser_service_url' => rtrim(env('YANDEX_PARSER_SERVICE_URL', 'http://parser:3000'), '/'),
    'scheduled_refresh' => env('YANDEX_SCHEDULED_REFRESH', '0 */6 * * *'),
    'alert_schedule' => env('YANDEX_ALERT_SCHEDULE', '*/15 * * * *'),
    'alerts' => [
        'failure_threshold' => (int) env('YANDEX_ALERT_FAILURE_THRESHOLD', 5),
        'window_minutes' => (int) env('YANDEX_ALERT_WINDOW_MINUTES', 30),
        'slack_webhook_url' => env('YANDEX_ALERT_SLACK_WEBHOOK_URL'),
        'telegram_bot_token' => env('YANDEX_ALERT_TELEGRAM_BOT_TOKEN'),
        'telegram_chat_id' => env('YANDEX_ALERT_TELEGRAM_CHAT_ID'),
    ],
    'otel' => [
        'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT'),
        'service_name' => env('OTEL_SERVICE_NAME', 'yandex-reviews-laravel'),
    ],
    'allowed_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('YANDEX_ALLOWED_HOSTS', 'yandex.ru,www.yandex.ru,yandex.kz,www.yandex.kz,yandex.com,www.yandex.com,yandex.by,www.yandex.by'))
    ))),
];
