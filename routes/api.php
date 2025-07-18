<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/webhook-handler', function (Request $request) {
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
            return response('Invalid signature', 403);
        }
        
        Log::info('Webhook signature validated successfully');
    }

    // Get the project root directory
    $projectRoot = base_path();
    $deployScript = $projectRoot . '/deploy.sh';
    
    // Check if deploy script exists
    if (!file_exists($deployScript)) {
        Log::error('Deploy script not found', ['path' => $deployScript]);
        return response('Deploy script not found: ' . $deployScript, 500);
    }
    
    // Check if deploy script is executable
    if (!is_executable($deployScript)) {
        Log::error('Deploy script is not executable', ['path' => $deployScript]);
        return response('Deploy script is not executable: ' . $deployScript, 500);
    }

    try {
        Log::info('Starting deployment', ['script' => $deployScript]);
        
        // Run the deploy script
        $process = new Process(['/bin/bash', $deployScript]);
        $process->setTimeout(300); // 5 minutes timeout
        $process->run();

        if ($process->isSuccessful()) {
            $output = $process->getOutput();
            Log::info('Deployment completed successfully', [
                'output' => $output,
                'script' => $deployScript
            ]);
            
            return response('Deployment completed successfully.', 200);
        } else {
            $error = $process->getErrorOutput();
            Log::error('Deployment failed', [
                'error' => $error,
                'output' => $process->getOutput(),
                'exit_code' => $process->getExitCode(),
                'script' => $deployScript
            ]);
            
            return response('Deployment failed: ' . $error, 500);
        }
    } catch (ProcessFailedException $exception) {
        Log::error('Process failed exception', [
            'message' => $exception->getMessage(),
            'script' => $deployScript
        ]);
        
        return response('Deployment failed: ' . $exception->getMessage(), 500);
    } catch (\Exception $exception) {
        Log::error('General exception during deployment', [
            'message' => $exception->getMessage(),
            'script' => $deployScript
        ]);
        
        return response('Deployment failed: ' . $exception->getMessage(), 500);
    }
});
