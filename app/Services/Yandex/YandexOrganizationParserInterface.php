<?php

namespace App\Services\Yandex;

use App\DTO\ParsedOrganizationData;

interface YandexOrganizationParserInterface
{
    public function parse(string $normalizedUrl): ParsedOrganizationData;
}
