<?php

namespace Tests\Feature;

use App\Actions\Organization\PersistParsedOrganizationAction;
use App\DTO\ParsedOrganizationData;
use App\Enums\ParsingStatus;
use App\Jobs\ParseYandexOrganizationJob;
use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use App\Services\Yandex\YandexOrganizationParserInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class AuthAndOrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $this->seedUser();

        $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('data.email', 'test@example.com');
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        $this->seedUser();

        $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong',
        ])->assertUnprocessable();
    }

    public function test_guest_cannot_get_organization(): void
    {
        $this->getJson('/api/organization')->assertUnauthorized();
    }

    public function test_user_can_get_empty_organization(): void
    {
        $user = $this->seedUser();

        $this->actingAs($user)->getJson('/api/organization')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_user_can_save_valid_yandex_url_and_job_is_dispatched(): void
    {
        Queue::fake();
        $user = $this->seedUser();

        $this->actingAs($user)->postJson('/api/organization', [
            'yandex_url' => 'https://yandex.ru/maps/org/test/?utm_source=x&tab=reviews&z=15',
        ])->assertOk()
            ->assertJsonPath('data.parsing_status', ParsingStatus::Pending->value)
            ->assertJsonPath('data.normalized_yandex_url', 'https://yandex.ru/maps/org/test?tab=reviews&z=15');

        Queue::assertPushed(ParseYandexOrganizationJob::class);
    }

    public function test_invalid_and_unsafe_urls_are_rejected(): void
    {
        $user = $this->seedUser();

        foreach ([
            'notaurl',
            'https://example.com/maps/org/test',
            'http://localhost/maps/org/test',
            'https://127.0.0.1/maps/org/test',
        ] as $url) {
            $this->actingAs($user)->postJson('/api/organization', ['yandex_url' => $url])
                ->assertUnprocessable();
        }
    }

    public function test_refresh_dispatches_parsing_job(): void
    {
        Queue::fake();
        $user = $this->seedUser();
        Organization::query()->create([
            'user_id' => $user->id,
            'yandex_url' => 'https://yandex.ru/maps/org/test',
            'normalized_yandex_url' => 'https://yandex.ru/maps/org/test',
            'parsing_status' => ParsingStatus::Success,
        ]);

        $this->actingAs($user)->postJson('/api/organization/refresh')
            ->assertOk()
            ->assertJsonPath('data.parsing_status', ParsingStatus::Pending->value);

        Queue::assertPushed(ParseYandexOrganizationJob::class);
    }

    public function test_reviews_endpoint_returns_only_current_user_reviews_with_meta(): void
    {
        $user = $this->seedUser();
        $other = User::factory()->create();
        $organization = $this->organizationFor($user);
        $otherOrganization = $this->organizationFor($other);

        Review::query()->create([
            'organization_id' => $organization->id,
            'fingerprint' => 'own',
            'author_name' => 'Own',
            'review_date' => '2026-01-10',
            'text' => 'Visible',
            'rating' => 5,
        ]);
        Review::query()->create([
            'organization_id' => $otherOrganization->id,
            'fingerprint' => 'foreign',
            'author_name' => 'Foreign',
            'text' => 'Hidden',
        ]);

        $this->actingAs($user)->getJson('/api/organization/reviews?per_page=50')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.author_name', 'Own')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_user_can_list_multiple_organizations_and_get_scoped_reviews(): void
    {
        $user = $this->seedUser();
        $first = $this->organizationFor($user, 'https://yandex.ru/maps/org/first');
        $second = $this->organizationFor($user, 'https://yandex.ru/maps/org/second');

        Review::query()->create([
            'organization_id' => $first->id,
            'fingerprint' => 'first-review',
            'author_name' => 'First',
            'text' => 'First organization review',
        ]);
        Review::query()->create([
            'organization_id' => $second->id,
            'fingerprint' => 'second-review',
            'author_name' => 'Second',
            'text' => 'Second organization review',
        ]);

        $this->actingAs($user)->getJson('/api/organizations')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($user)->getJson("/api/organizations/{$second->id}/reviews")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.author_name', 'Second');
    }

    public function test_per_page_above_50_is_rejected(): void
    {
        $user = $this->seedUser();
        $this->organizationFor($user);

        $this->actingAs($user)->getJson('/api/organization/reviews?per_page=51')
            ->assertUnprocessable();
    }

    public function test_fake_parser_job_persists_data_and_deduplicates_reviews(): void
    {
        $organization = $this->organizationFor($this->seedUser());

        (new ParseYandexOrganizationJob($organization->id))->handle(
            app(YandexOrganizationParserInterface::class),
            app(PersistParsedOrganizationAction::class),
        );
        (new ParseYandexOrganizationJob($organization->id))->handle(
            app(YandexOrganizationParserInterface::class),
            app(PersistParsedOrganizationAction::class),
        );

        $organization->refresh();
        $this->assertSame(ParsingStatus::Success, $organization->parsing_status);
        $this->assertSame(3, $organization->reviews()->count());
        $this->assertSame(4.7, $organization->rating);
        $this->assertSame(2, $organization->ratingSnapshots()->count());
    }

    public function test_rating_history_and_parser_monitoring_are_available(): void
    {
        $user = $this->seedUser();
        $success = $this->organizationFor($user, 'https://yandex.ru/maps/org/success');
        $failed = $this->organizationFor($user, 'https://yandex.ru/maps/org/failed');

        $success->ratingSnapshots()->create([
            'rating' => 4.6,
            'ratings_count' => 100,
            'reviews_count' => 50,
            'captured_at' => now(),
        ]);
        $failed->update([
            'parsing_status' => ParsingStatus::Failed,
            'parsing_error' => 'Blocked by Yandex.',
        ]);

        $this->actingAs($user)->getJson("/api/organizations/{$success->id}/rating-history")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rating', 4.6);

        $this->actingAs($user)->getJson('/api/parser-monitoring')
            ->assertOk()
            ->assertJsonPath('data.counts.failed', 1)
            ->assertJsonPath('data.recent_errors.0.parsing_error', 'Blocked by Yandex.');
    }

    public function test_user_can_export_organization_reviews_as_csv_and_json(): void
    {
        $user = $this->seedUser();
        $organization = $this->organizationFor($user);
        $organization->update([
            'name' => 'Test Place',
            'rating' => 4.8,
            'ratings_count' => 120,
            'reviews_count' => 80,
            'last_parsed_at' => now(),
        ]);
        $organization->reviews()->create([
            'fingerprint' => 'export-review',
            'author_name' => 'Export Author',
            'review_date' => '2026-06-10',
            'text' => 'Export text',
            'rating' => 5,
        ]);
        $organization->ratingSnapshots()->create([
            'rating' => 4.8,
            'ratings_count' => 120,
            'reviews_count' => 80,
            'captured_at' => now(),
        ]);

        $csv = $this->actingAs($user)->get("/api/organizations/{$organization->id}/export?format=csv");
        $csv->assertOk();
        $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Export Author', $csv->streamedContent());

        $json = $this->actingAs($user)->get("/api/organizations/{$organization->id}/export?format=json");
        $json->assertOk();
        $payload = json_decode($json->streamedContent(), true);

        $this->assertSame('Test Place', $payload['organization']['name']);
        $this->assertSame('Export text', $payload['reviews'][0]['text']);
        $this->assertSame(4.8, $payload['rating_history'][0]['rating']);
    }

    public function test_user_cannot_export_foreign_organization(): void
    {
        $user = $this->seedUser();
        $other = User::factory()->create();
        $organization = $this->organizationFor($other);

        $this->actingAs($user)->get("/api/organizations/{$organization->id}/export?format=csv")
            ->assertNotFound();
    }

    public function test_parser_error_marks_organization_as_failed(): void
    {
        $organization = $this->organizationFor($this->seedUser());

        $this->app->bind(YandexOrganizationParserInterface::class, fn () => new class implements YandexOrganizationParserInterface
        {
            public function parse(string $normalizedUrl): ParsedOrganizationData
            {
                throw new RuntimeException('Parser unavailable.');
            }
        });

        (new ParseYandexOrganizationJob($organization->id))->handle(
            app(YandexOrganizationParserInterface::class),
            app(PersistParsedOrganizationAction::class),
        );

        $organization->refresh();
        $this->assertSame(ParsingStatus::Failed, $organization->parsing_status);
        $this->assertSame('Parser unavailable.', $organization->parsing_error);
    }

    private function seedUser(): User
    {
        return User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    private function organizationFor(User $user, string $url = 'https://yandex.ru/maps/org/test'): Organization
    {
        return Organization::query()->create([
            'user_id' => $user->id,
            'yandex_url' => $url,
            'normalized_yandex_url' => $url,
            'parsing_status' => ParsingStatus::Success,
        ]);
    }
}
