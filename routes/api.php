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

Route::get('/webhook-handler', function () {
    // Run the deploy script
    $process = new Process(['/bin/bash', '/home/digital07/auto-deploy.digital-vision-solutions.com/deploy.sh']);
    
    try {
        $process->mustRun(); // This will throw an exception if the command fails
    } catch (ProcessFailedException $exception) {
        return response('Deployment failed: ' . $exception->getMessage(), 500);
    }

    return response('Deployment completed successfully.', 200);
});