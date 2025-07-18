<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TriggerWebhooks extends Command
{
    protected $signature = 'webhooks:trigger';
    protected $description = 'Send POST requests to a list of webhook URLs';

    public function handle()
    {
        // Array of webhook URLs and their secrets
        $webhooks = [
            [
                'url' => 'https://auto-deploy.digital-vision-solutions.com/api/webhook-handler',
                'secret' => 'YourSecretToken1',
            ],
            
            // Add more webhook URLs and secrets as needed
        ];

        // Sample payload (customize as needed)
        $payload = [
            'event' => 'push',
            'data' => [
                'repository' => 'webhook-auto-deploy',
                'commit' => 'example-commit',
                'timestamp' => now()->toIso8601String(),
            ],
        ];

        foreach ($webhooks as $webhook) {
            try {
                // Generate GitHub-style HMAC signature
                $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), $webhook['secret']);

                // Send POST request
                $response = Http::withHeaders([
                    'X-Hub-Signature-256' => $signature,
                    'X-GitHub-Event' => 'push',
                    'Content-Type' => 'application/json',
                ])->post($webhook['url'], $payload);

                if ($response->successful()) {
                    $this->info("Successfully triggered webhook: {$webhook['url']}");
                    Log::info('Webhook triggered successfully', [
                        'url' => $webhook['url'],
                        'status' => $response->status(),
                        'response' => $response->body(),
                    ]);
                } else {
                    $this->error("Failed to trigger webhook: {$webhook['url']}");
                    $this->error("Status: {$response->status()}");
                    $this->error("Response: {$response->body()}");
                    Log::error('Webhook trigger failed', [
                        'url' => $webhook['url'],
                        'status' => $response->status(),
                        'response' => $response->body(),
                    ]);
                }
            } catch (\Exception $e) {
                $this->error("Error triggering webhook: {$webhook['url']}");
                $this->error("Error: {$e->getMessage()}");
                Log::error('Webhook trigger error', [
                    'url' => $webhook['url'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return 0;
    }
}