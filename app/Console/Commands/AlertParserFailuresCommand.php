<?php

namespace App\Console\Commands;

use App\Enums\ParsingStatus;
use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlertParserFailuresCommand extends Command
{
    protected $signature = 'yandex:alert-parser-failures';

    protected $description = 'Send Slack/Telegram alerts when parser failures exceed configured threshold.';

    public function handle(): int
    {
        $threshold = (int) config('yandex.alerts.failure_threshold');
        $failedCount = Organization::query()
            ->where('parsing_status', ParsingStatus::Failed)
            ->where('updated_at', '>=', now()->subMinutes((int) config('yandex.alerts.window_minutes')))
            ->count();

        if ($failedCount < $threshold) {
            $this->info("Parser failures are below threshold: {$failedCount}/{$threshold}.");

            return self::SUCCESS;
        }

        $message = "Yandex parser failures exceeded threshold: {$failedCount}/{$threshold} in the last ".config('yandex.alerts.window_minutes').' minutes.';
        $this->sendSlack($message);
        $this->sendTelegram($message);
        Log::warning($message);
        $this->warn($message);

        return self::SUCCESS;
    }

    private function sendSlack(string $message): void
    {
        $webhook = config('yandex.alerts.slack_webhook_url');
        if (! $webhook) {
            return;
        }

        Http::timeout(10)->post($webhook, ['text' => $message]);
    }

    private function sendTelegram(string $message): void
    {
        $token = config('yandex.alerts.telegram_bot_token');
        $chatId = config('yandex.alerts.telegram_chat_id');
        if (! $token || ! $chatId) {
            return;
        }

        Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
        ]);
    }
}
