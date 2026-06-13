<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('yandex:refresh-organizations')
    ->cron(config('yandex.scheduled_refresh'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('yandex:alert-parser-failures')
    ->cron(config('yandex.alert_schedule'))
    ->withoutOverlapping()
    ->onOneServer();
