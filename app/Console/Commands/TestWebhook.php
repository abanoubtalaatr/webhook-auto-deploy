<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestWebhook extends Command
{
    protected $signature = 'webhook:test {url?}';
    protected $description = 'Test webhook endpoint with simple request';

    public function handle()
    {
        $url = $this->argument('url') ?: 'https://auto-deploy.digital-vision-solutions.com/api/webhook-handler';
        
        $this->info("Testing webhook: {$url}");
        
        try {
            // Simple test payload
            $payload = [
                'test' => true,
                'timestamp' => now()->toIso8601String(),
            ];
            
            // Generate signature
            $secret = 'YourSecretToken1';
            $signature = 'sha256=' . hash_hmac('sha256', json_encode($payload), $secret);
            
            $this->line("Payload: " . json_encode($payload));
            $this->line("Signature: {$signature}");
            $this->line("Making request...");
            
            $response = Http::timeout(30)->withHeaders([
                'X-Hub-Signature-256' => $signature,
                'X-GitHub-Event' => 'test',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'WebhookTest/1.0',
            ])->post($url, $payload);
            
            $this->line("Response Status: {$response->status()}");
            $this->line("Response Headers: " . json_encode($response->headers()));
            
            if ($response->successful()) {
                $this->info("✅ Webhook test successful!");
                
                $responseData = $response->json();
                if ($responseData) {
                    $this->line("JSON Response: " . json_encode($responseData, JSON_PRETTY_PRINT));
                } else {
                    $this->line("Raw Response: " . $response->body());
                }
            } else {
                $this->error("❌ Webhook test failed!");
                $this->error("Response Body: " . $response->body());
            }
            
        } catch (\Exception $e) {
            $this->error("Exception occurred: " . $e->getMessage());
            $this->error("File: " . $e->getFile() . " Line: " . $e->getLine());
        }
        
        return 0;
    }
} 