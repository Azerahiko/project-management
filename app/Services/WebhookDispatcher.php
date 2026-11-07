<?php

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Models\Webhook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class WebhookDispatcher
{
    /**
     * Deliver an event payload to every active webhook that listens to it.
     * Dispatches jobs after the database transaction commits.
     *
     * @param  array<string, mixed>  $data
     */
    public function dispatch(string $event, array $data): void
    {
        $webhooks = Webhook::query()
            ->active()
            ->get()
            ->filter(fn (Webhook $webhook): bool => $webhook->listensTo($event));

        foreach ($webhooks as $webhook) {
            $payloadId = (string) Str::uuid();

            DB::afterCommit(function () use ($webhook, $event, $data, $payloadId) {
                DeliverWebhook::dispatch($webhook->id, $event, $data, $payloadId);
            });
        }
    }

    /**
     * Send a single payload to a webhook synchronously (for testing).
     *
     * @param  array<string, mixed>  $data
     * @return array{success: bool, status: int|null, latency_ms: int, error: string|null}
     */
    public function sendSync(Webhook $webhook, string $event, array $data): array
    {
        $payload = [
            'id' => (string) Str::uuid(),
            'event' => $event,
            'created_at' => now()->toIso8601String(),
            'data' => $data,
        ];

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $signature = hash_hmac('sha256', $body, $webhook->secret);
        $startedAt = microtime(true);

        try {
            $response = Http::timeout(6)
                ->connectTimeout(3)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'DigitalCode-Webhook/1.0',
                    'X-Webhook-Event' => $event,
                    'X-Webhook-Signature' => 'sha256='.$signature,
                    'X-Webhook-Delivery' => $payload['id'],
                ])
                ->withBody($body, 'application/json')
                ->post($webhook->url);

            $latency = (int) round((microtime(true) - $startedAt) * 1000);
            $success = $response->successful();
            $error = $success ? null : Str::limit($response->body(), 500);

            $this->record($webhook, $success, $response->status(), $latency, $error);

            return [
                'success' => $success,
                'status' => $response->status(),
                'latency_ms' => $latency,
                'error' => $error,
            ];
        } catch (Throwable $exception) {
            $latency = (int) round((microtime(true) - $startedAt) * 1000);
            $error = Str::limit($exception->getMessage(), 500);

            $this->record($webhook, false, null, $latency, $error);

            return [
                'success' => false,
                'status' => null,
                'latency_ms' => $latency,
                'error' => $error,
            ];
        }
    }

    private function record(
        Webhook $webhook,
        bool $success,
        ?int $status,
        int $latency,
        ?string $error,
    ): void {
        $webhook->forceFill([
            'last_delivery_status' => $success ? 'success' : 'failed',
            'last_delivery_code' => $status,
            'last_delivery_latency_ms' => $latency,
            'last_delivery_error' => $error,
            'last_delivery_at' => now(),
        ])->save();
    }
}
