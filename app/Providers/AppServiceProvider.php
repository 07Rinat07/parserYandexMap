<?php

namespace App\Providers;

use App\Services\Yandex\FakeYandexOrganizationParser;
use App\Services\Yandex\ParserMicroserviceYandexOrganizationParser;
use App\Services\Yandex\PlaywrightYandexOrganizationParser;
use App\Services\Yandex\YandexOrganizationParserInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(YandexOrganizationParserInterface::class, function () {
            return match (config('yandex.parser_mode')) {
                'fake' => app(FakeYandexOrganizationParser::class),
                'microservice' => app(ParserMicroserviceYandexOrganizationParser::class),
                default => app(PlaywrightYandexOrganizationParser::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip().'|'.strtolower((string) $request->input('email')));
        });
    }
}
