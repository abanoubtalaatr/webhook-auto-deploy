<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'php_version' => PHP_VERSION,
        'laravel_version' => app()->version(),
        'deploy_script_exists' => file_exists(base_path('deploy.sh')),
        'deploy_script_executable' => file_exists(base_path('deploy.sh')) && is_executable(base_path('deploy.sh')),
    ]);
});

Route::post('/webhook-handler', function (Request $request) {
    // Set JSON response headers
    $headers = [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json'
    ];
    
    try {
        Log::info('Webhook received', [
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
        ]);

        // Optional: Validate webhook signature for security
        $receivedSignature = $request->header('X-Hub-Signature-256');
        if ($receivedSignature) {
            $secret = env('WEBHOOK_SECRET', 'YourSecretToken1');
            $payloadJson = json_encode($request->all());
            $expectedSignature = 'sha256=' . hash_hmac('sha256', $payloadJson, $secret);
            
            if (!hash_equals($expectedSignature, $receivedSignature)) {
                Log::warning('Invalid webhook signature', [
                    'received' => $receivedSignature,
                    'expected' => $expectedSignature,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid signature'
                ], 403, $headers);
            }
            
            Log::info('Webhook signature validated successfully');
        }

        // Get the project root directory
        $projectRoot = base_path();
        $deployScript = $projectRoot . '/deploy.sh';
        
        // Check if deploy script exists
        if (!file_exists($deployScript)) {
            Log::error('Deploy script not found', ['path' => $deployScript]);
            return response()->json([
                'success' => false,
                'message' => 'Deploy script not found: ' . $deployScript
            ], 500, $headers);
        }
        
        // Check if deploy script is executable
        if (!is_executable($deployScript)) {
            Log::error('Deploy script is not executable', ['path' => $deployScript]);
            return response()->json([
                'success' => false,
                'message' => 'Deploy script is not executable: ' . $deployScript
            ], 500, $headers);
        }

        Log::info('Starting deployment', ['script' => $deployScript]);
        
        // Run the deploy script
        $process = new Process(['/bin/bash', $deployScript]);
        $process->setTimeout(300); // 5 minutes timeout
        
        // Run the process and wait for completion
        $exitCode = $process->run();

        if ($exitCode === 0) {
            $output = $process->getOutput();
            Log::info('Deployment completed successfully', [
                'output' => $output,
                'script' => $deployScript,
                'exit_code' => $exitCode
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Deployment completed successfully.',
                'output' => $output
            ], 200, $headers);
        } else {
            $error = $process->getErrorOutput();
            $output = $process->getOutput();
            Log::error('Deployment failed', [
                'error' => $error,
                'output' => $output,
                'exit_code' => $exitCode,
                'script' => $deployScript
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Deployment failed with exit code ' . $exitCode,
                'error' => $error,
                'output' => $output,
                'exit_code' => $exitCode
            ], 500, $headers);
        }
    } catch (ProcessFailedException $exception) {
        Log::error('Process failed exception', [
            'message' => $exception->getMessage(),
            'script' => $deployScript ?? 'unknown'
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Deployment failed: ' . $exception->getMessage()
        ], 500, $headers);
    } catch (\Exception $exception) {
        Log::error('General exception during deployment', [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'script' => $deployScript ?? 'unknown'
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Deployment failed: ' . $exception->getMessage()
        ], 500, $headers);
    }
});
