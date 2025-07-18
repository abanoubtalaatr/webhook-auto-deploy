<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/webhook-handler', function () {
    // Run the deploy script
    $process = new Process(['/bin/bash', '/home/digital07/auto-deploy.digital-vision-solutions.com/deploy.sh']);
    
    try {
        $process->mustRun(); // This will throw an exception if the command fails
    } catch (ProcessFailedException $exception) {
        return response('Deployment failed: ' . $exception->getMessage(), 500);
    }

    return response('Deployment completed successfully.', 200);
});
