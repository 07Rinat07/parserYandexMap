<?php

return [
    'max_reviews' => (int) env('YANDEX_MAX_REVIEWS', 700),
    'timeout' => (int) env('YANDEX_PARSER_TIMEOUT', 180),
    'parser_mode' => env('YANDEX_PARSER_MODE', 'playwright'),
    'parser_service_url' => rtrim(env('YANDEX_PARSER_SERVICE_URL', 'http://parser:3000'), '/'),
    'scheduled_refresh' => env('YANDEX_SCHEDULED_REFRESH', '0 */6 * * *'),
    'allowed_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('YANDEX_ALLOWED_HOSTS', 'yandex.ru,www.yandex.ru,yandex.kz,www.yandex.kz,yandex.com,www.yandex.com,yandex.by,www.yandex.by'))
    ))),
];
