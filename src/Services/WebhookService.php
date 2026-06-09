<?php

namespace CorpWalletManager\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use CorpWalletManager\Models\Webhook;

/**
 * Single Discord webhook dispatch path for Corp Wallet Manager.
 *
 * Replaces the two hand-rolled curl blocks that previously lived in
 * GenerateReport and ReportsController. Owns:
 *   - resolving which webhooks a corporation's report should reach
 *   - retry / rate-limit handling (5xx + 429, Retry-After honored)
 *   - per-webhook delivery-health bookkeeping
 *   - locking down allowed_mentions so report text can never trigger
 *     @everyone / @here or an unintended ping
 *
 * Mirrors the Mining Manager / Structure Manager webhook pattern so
 * delivery behaviour is consistent across the plugin suite.
 */
class WebhookService
{
    /**
     * Cap on how long a 429 Retry-After header may block a worker. Discord
     * usually issues sub-3s values; anything larger is better left to the
     * next scheduled run than blocking a PHP-FPM/queue worker.
     */
    private const RETRY_AFTER_HARD_CAP_SECONDS = 5;

    /**
     * Webhooks subscribed to a report for a corporation (plus every global
     * webhook). With no corporation, only global webhooks are returned.
     *
     * @return Collection<int, Webhook>
     */
    public function webhooksForReport(?int $corporationId, string $reportType): Collection
    {
        $category = Webhook::reportCategoryFor($reportType);

        return Webhook::query()
            ->enabled()
            ->forCorporation($corporationId)
            ->forReport($category)
            ->get();
    }

    /**
     * Webhooks subscribed to a given alert type for a corporation
     * (plus every global webhook).
     *
     * @return Collection<int, Webhook>
     */
    public function webhooksForAlert(?int $corporationId, string $alertType): Collection
    {
        return Webhook::query()
            ->enabled()
            ->forCorporation($corporationId)
            ->forAlert($alertType)
            ->get();
    }

    /**
     * Deliver a report embed to every webhook subscribed to this
     * corporation + report category.
     *
     * @param  array  $embed  A single Discord embed array.
     * @return array{sent:int,failed:int}
     */
    public function dispatchReport(?int $corporationId, string $reportType, array $embed): array
    {
        return $this->deliver(
            $this->webhooksForReport($corporationId, $reportType),
            $embed,
            'report:' . $reportType
        );
    }

    /**
     * Deliver an alert embed to every webhook subscribed to this
     * corporation + alert type.
     *
     * @param  array  $embed  A single Discord embed array.
     * @return array{sent:int,failed:int}
     */
    public function dispatchAlert(?int $corporationId, string $alertType, array $embed): array
    {
        return $this->deliver(
            $this->webhooksForAlert($corporationId, $alertType),
            $embed,
            'alert:' . $alertType
        );
    }

    /**
     * Send one embed to a resolved set of webhooks, recording health on each.
     *
     * @param  Collection<int, Webhook>  $webhooks
     * @return array{sent:int,failed:int}
     */
    private function deliver(Collection $webhooks, array $embed, string $context): array
    {
        if ($webhooks->isEmpty()) {
            Log::info('[Corp Wallet Manager] No webhooks subscribed', ['context' => $context]);

            return ['sent' => 0, 'failed' => 0];
        }

        $sent = 0;
        $failed = 0;

        foreach ($webhooks as $webhook) {
            if ($this->send($webhook, $this->buildPayload($webhook, [$embed]))) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Send a test message to a single webhook. Throws on failure so the
     * caller can surface the reason to the operator.
     *
     * @throws \RuntimeException
     */
    public function sendTest(Webhook $webhook): void
    {
        $embed = [
            'title'       => 'Corp Wallet Manager - Webhook Test',
            'description' => 'This webhook is configured correctly and ready to deliver reports.',
            'color'       => 3447003,
            'timestamp'   => now()->toIso8601String(),
            'footer'      => ['text' => 'Corp Wallet Manager'],
        ];

        $this->sendOneEmbed($webhook, $embed);
    }

    /**
     * Send an arbitrary embed to a single webhook with a single attempt and
     * surface the result to the caller. Used by the Diagnostic page's
     * Notification Testing tab so it can report per-webhook outcomes
     * (success vs which webhook failed and why) without going through the
     * fan-out dispatch path that only returns aggregate counts.
     *
     * @throws \RuntimeException
     */
    public function sendOneEmbed(Webhook $webhook, array $embed): void
    {
        // Single attempt - a test should give immediate feedback rather
        // than block the operator behind retries.
        $response = $this->postWithRetry($webhook->webhook_url, $this->buildPayload($webhook, [$embed]), 1);

        if (! $response->successful() && $response->status() !== 204) {
            $webhook->recordFailure('HTTP ' . $response->status());

            throw new \RuntimeException('Discord returned HTTP ' . $response->status());
        }

        $webhook->recordSuccess();
    }

    /**
     * Post a payload to one webhook, recording delivery health either way.
     */
    private function send(Webhook $webhook, array $payload, int $maxAttempts = 3): bool
    {
        try {
            $response = $this->postWithRetry($webhook->webhook_url, $payload, $maxAttempts);

            if ($response->successful() || $response->status() === 204) {
                $webhook->recordSuccess();

                return true;
            }

            $webhook->recordFailure('HTTP ' . $response->status());
            Log::warning('[Corp Wallet Manager] Webhook delivery failed', [
                'webhook_id' => $webhook->id,
                'status'     => $response->status(),
            ]);

            return false;
        } catch (\Throwable $e) {
            $webhook->recordFailure($e->getMessage());
            Log::warning('[Corp Wallet Manager] Webhook delivery threw', [
                'webhook_id' => $webhook->id,
                'error'      => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Build a Discord JSON payload. A role mention (when configured) goes
     * in `content`; allowed_mentions is locked down so embed/report text
     * can never trigger @everyone/@here or an unintended ping.
     *
     * @param  array  $embeds  List of Discord embed arrays.
     */
    private function buildPayload(Webhook $webhook, array $embeds): array
    {
        $payload = ['embeds' => $embeds];

        $mention = $webhook->getDiscordRoleMention();
        $snowflake = $webhook->getDiscordRoleSnowflake();

        if ($mention !== null && $snowflake !== null) {
            $payload['content'] = $mention;
            $payload['allowed_mentions'] = ['parse' => [], 'roles' => [$snowflake]];
        } else {
            $payload['allowed_mentions'] = ['parse' => []];
        }

        return $payload;
    }

    /**
     * HTTP POST with retry on 5xx + 429. Honors the Retry-After header
     * (capped at RETRY_AFTER_HARD_CAP_SECONDS). Client errors other than
     * 429 return immediately — no point retrying a 4xx.
     */
    private function postWithRetry(
        string $url,
        array $payload,
        int $maxAttempts = 3,
        int $retryDelaySeconds = 2
    ): Response {
        $lastException = null;
        $response = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::timeout(10)->post($url, $payload);

                if ($response->successful()
                    || $response->status() === 204
                    || ($response->clientError() && $response->status() !== 429)) {
                    return $response;
                }

                if ($response->status() === 429) {
                    $retryAfter = (int) ($response->header('Retry-After') ?: $retryDelaySeconds);
                    $wait = min($retryAfter, self::RETRY_AFTER_HARD_CAP_SECONDS);
                    if ($attempt < $maxAttempts) {
                        sleep($wait);
                    }

                    continue;
                }
            } catch (\Throwable $e) {
                $lastException = $e;
            }

            if ($attempt < $maxAttempts) {
                sleep($retryDelaySeconds);
            }
        }

        if ($response !== null) {
            return $response;
        }

        throw $lastException ?? new \RuntimeException('Webhook request failed after all retry attempts');
    }
}
