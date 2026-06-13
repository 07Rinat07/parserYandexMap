<?php

namespace Tests\Unit;

use App\Actions\Organization\PersistParsedOrganizationAction;
use App\DTO\ParsedOrganizationData;
use App\DTO\ParsedReviewData;
use App\Enums\ParsingStatus;
use App\Exceptions\InvalidYandexMapsUrlException;
use App\Exceptions\YandexParserTimeoutException;
use App\Exceptions\YandexParsingException;
use App\Models\Organization;
use App\Models\User;
use App\Services\Yandex\PlaywrightYandexOrganizationParser;
use App\Services\Yandex\ReviewFingerprintGenerator;
use App\Services\Yandex\YandexMapsUrlNormalizer;
use App\Services\Yandex\YandexMapsUrlValidator;
use App\Support\SafeUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YandexSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_validator_accepts_yandex_maps_urls(): void
    {
        $validator = app(YandexMapsUrlValidator::class);

        foreach ([
            'https://yandex.ru/maps/org/test',
            'https://yandex.kz/maps/-/abc?ll=1,2&z=12',
            'https://www.yandex.com/maps/org/test?tab=reviews',
        ] as $url) {
            $validator->validate($url);
            $this->assertTrue(true);
        }
    }

    public function test_validator_rejects_bad_urls(): void
    {
        $this->expectException(InvalidYandexMapsUrlException::class);

        app(YandexMapsUrlValidator::class)->validate('ftp://yandex.ru/maps/org/test');
    }

    public function test_normalizer_keeps_allowed_query_params_only(): void
    {
        $url = app(YandexMapsUrlNormalizer::class)->normalize('https://yandex.ru/maps/org/test/?utm=1&z=12&tab=reviews');

        $this->assertSame('https://yandex.ru/maps/org/test?tab=reviews&z=12', $url);
    }

    public function test_safe_url_blocks_private_hosts(): void
    {
        $safeUrl = new SafeUrl;

        $this->assertTrue($safeUrl->isUnsafeHost('localhost'));
        $this->assertTrue($safeUrl->isUnsafeHost('192.168.0.1'));
        $this->assertFalse($safeUrl->isUnsafeHost('yandex.ru'));
    }

    public function test_fingerprint_is_stable(): void
    {
        $review = ParsedReviewData::fromArray([
            'author_name' => 'Ivan',
            'review_date' => '2026-01-10',
            'text' => 'Nice   place',
            'rating' => 5,
        ]);

        $generator = new ReviewFingerprintGenerator;

        $this->assertSame($generator->generate($review), $generator->generate($review));
    }

    public function test_dto_creation_handles_empty_fields(): void
    {
        $review = ParsedReviewData::fromArray(['rating' => 9]);
        $organization = ParsedOrganizationData::fromArray([
            'reviews' => [[]],
            'parser' => [
                'confidence' => 82,
                'warnings' => ['missing_title'],
            ],
        ]);

        $this->assertSame(5, $review->rating);
        $this->assertCount(1, $organization->reviews);
        $this->assertSame(82, $organization->parserConfidence);
        $this->assertSame(['missing_title'], $organization->parserMetadata['warnings']);
    }

    public function test_low_parser_confidence_is_rejected_before_persisting(): void
    {
        config(['yandex.minimum_parser_confidence' => 50]);
        $user = User::factory()->create();
        $organization = Organization::query()->create([
            'user_id' => $user->id,
            'yandex_url' => 'https://yandex.ru/maps/org/test',
            'normalized_yandex_url' => 'https://yandex.ru/maps/org/test',
            'parsing_status' => ParsingStatus::Processing,
        ]);
        $data = ParsedOrganizationData::fromArray([
            'rating' => 4.2,
            'reviews_count' => 10,
            'reviews' => [],
            'parser' => [
                'confidence' => 20,
                'warnings' => ['missing_reviews'],
            ],
        ]);

        $this->expectException(YandexParsingException::class);

        app(PersistParsedOrganizationAction::class)->execute($organization, $data);
    }

    public function test_playwright_wrapper_rejects_invalid_json(): void
    {
        $script = base_path('parser/yandex-parser.js');
        $original = file_get_contents($script);
        file_put_contents($script, 'process.stdout.write("not-json")');

        try {
            $this->expectException(YandexParsingException::class);
            app(PlaywrightYandexOrganizationParser::class)->parse('https://yandex.ru/maps/org/test');
        } finally {
            file_put_contents($script, $original);
        }
    }

    public function test_playwright_wrapper_handles_timeout(): void
    {
        config(['yandex.timeout' => 1]);
        $script = base_path('parser/yandex-parser.js');
        $original = file_get_contents($script);
        file_put_contents($script, 'setTimeout(() => {}, 5000)');

        try {
            $this->expectException(YandexParserTimeoutException::class);
            app(PlaywrightYandexOrganizationParser::class)->parse('https://yandex.ru/maps/org/test');
        } finally {
            file_put_contents($script, $original);
        }
    }
}
