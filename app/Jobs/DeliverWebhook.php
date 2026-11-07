<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public int $timeout = 30;

    /**
     * Backoff in seconds for each attempt: 60, 300, 900.
     *
     * @var list<int>
     */
    public array $backoff = [60, 300, 900];

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public int $webhookId,
        public string $event,
        public array $data,
        public string $payloadId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $webhook = Webhook::find($this->webhookId);

        if (! $webhook) {
            Log::warning('Webhook not found for delivery', [
                'webhook_id' => $this->webhookId,
                'payload_id' => $this->payloadId,
            ]);

            return;
        }

        if (! $webhook->is_active) {
            Log::info('Webhook is inactive, skipping delivery', [
                'webhook_id' => $this->webhookId,
                'payload_id' => $this->payloadId,
            ]);

            return;
        }

        if (! $webhook->listensTo($this->event)) {
            Log::info('Webhook does not listen to event, skipping delivery', [
                'webhook_id' => $this->webhookId,
                'event' => $this->event,
                'payload_id' => $this->payloadId,
            ]);

            return;
        }

        $delivery = WebhookDelivery::firstOrCreate(
            ['payload_id' => $this->payloadId],
            [
                'webhook_id' => $this->webhookId,
                'event' => $this->event,
                'payload' => $this->data,
                'attempt' => $this->attempts(),
                'max_attempts' => $this->tries,
                'status' => 'pending',
            ]
        );

        if ($delivery->status === 'success') {
            Log::info('Webhook delivery already successful, skipping', [
                'delivery_id' => $delivery->id,
                'payload_id' => $this->payloadId,
            ]);

            return;
        }

        if ($delivery->status === 'permanently_failed') {
            Log::info('Webhook delivery permanently failed, skipping', [
                'delivery_id' => $delivery->id,
                'payload_id' => $this->payloadId,
            ]);

            return;
        }

        $delivery->update([
            'attempt' => $this->attempts(),
            'status' => 'pending',
        ]);

        $payload = [
            'id' => $this->payloadId,
            'event' => $this->event,
            'created_at' => now()->toIso8601String(),
            'data' => $this->data,
        ];

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $signature = hash_hmac('sha256', $body, $webhook->secret);
        $startedAt = microtime(true);

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'DigitalCode-Webhook/1.0',
                    'X-Webhook-Event' => $this->event,
                    'X-Webhook-Signature' => 'sha256='.$signature,
                    'X-Webhook-Delivery' => $this->payloadId,
                ])
                ->withBody($body, 'application/json')
                ->post($webhook->url);

            $latency = (int) round((microtime(true) - $startedAt) * 1000);
            $success = $response->successful();
            $error = $success ? null : Str::limit($response->body(), 1000);

            $delivery->update([
                'status' => $success ? 'success' : 'failed',
                'response_code' => $response->status(),
                'latency_ms' => $latency,
                'response_body' => $error,
                'error' => $success ? null : $error,
                'delivered_at' => now(),
            ]);

            $webhook->forceFill([
                'last_delivery_status' => $success ? 'success' : 'failed',
                'last_delivery_code' => $response->status(),
                'last_delivery_latency_ms' => $latency,
                'last_delivery_error' => $error,
                'last_delivery_at' => now(),
            ])->save();

            if (! $success) {
                $this->scheduleNextRetry($delivery);
                $this->fail(new \Exception("Webhook delivery failed with status {$response->status()}"));
            }
        } catch (Throwable $exception) {
            $latency = (int) round((microtime(true) - $startedAt) * 1000);
            $error = Str::limit($exception->getMessage(), 1000);

            $delivery->update([
                'status' => 'failed',
                'latency_ms' => $latency,
                'error' => $error,
            ]);

            $webhook->forceFill([
                'last_delivery_status' => 'failed',
                'last_delivery_code' => null,
                'last_delivery_latency_ms' => $latency,
                'last_delivery_error' => $error,
                'last_delivery_at' => now(),
            ])->save();

            $this->scheduleNextRetry($delivery);
            throw $exception;
        }
    }

    /**
     * Schedule the next retry attempt.
     */
    private function scheduleNextRetry(WebhookDelivery $delivery): void
    {
        if ($this->attempts() >= $this->tries) {
            $delivery->update([
                'status' => 'permanently_failed',
                'next_retry_at' => null,
            ]);
            Log::error('Webhook delivery permanently failed after max attempts', [
                'delivery_id' => $delivery->id,
                'webhook_id' => $this->webhookId,
                'payload_id' => $this->payloadId,
                'attempts' => $this->attempts(),
            ]);

            return;
        }

        $backoffSeconds = $this->backoff[$this->attempts() - 1] ?? 900;
        $delivery->update([
            'next_retry_at' => now()->addSeconds($backoffSeconds),
        ]);

        $this->release($backoffSeconds);
    }

    /**
     * Determine the job's tags.
     *
     * @return list<string>
     */
    public function tags(): array
    {
        return ['webhook', "webhook:{$this->webhookId}", "payload:{$this->payloadId}"];
    }
}
