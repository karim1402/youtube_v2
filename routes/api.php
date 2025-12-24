<?php

use App\Http\Controllers\Api\imageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\youtubeController;
use App\Http\Controllers\Api\WhiteNoiseController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::get('/youtube/auth-url', [youtubeController::class, 'getAuthUrl']);
Route::get('/youtube/callback', [youtubeController::class, 'handleCallback']);
Route::get('/youtube/callback1', [youtubeController::class, 'handleCallback1']);
Route::get('/youtube/callback0', [youtubeController::class, 'handleCallback0']); //refresh_token
Route::post('/youtube/refresh_token', [youtubeController::class, 'refresh_token']);
Route::post('/youtube/upload', [youtubeController::class, 'uploadVideo']);
Route::post('/image', [imageController::class, 'overlayImages']);
Route::get('/start', [youtubeController::class, 'liveStreem2']);

Route::post('/video/repeat-to-5min', [youtubeController::class, 'repeatVideoToFiveMinutes']);

Route::get('/queue-work', function () {
    \Artisan::call('optimize');
    \Artisan::call('optimize:clear');
    \Artisan::call('queue:restart');
    \Artisan::call('queue:work --timeout=3600');
    return response()->json(['message' => 'Queue worker started']);
});

// White Noise API Routes
Route::prefix('white-noise')->group(function () {
    // Generate noise by type
    Route::post('/generate', [WhiteNoiseController::class, 'generateNoise']);
    
    // Generate specific noise types
    Route::post('/generate/white', [WhiteNoiseController::class, 'generateWhiteNoise']);
    Route::post('/generate/pink', [WhiteNoiseController::class, 'generatePinkNoise']);
    Route::post('/generate/brown', [WhiteNoiseController::class, 'generateBrownNoise']);
    
    // File management
    Route::get('/files', [WhiteNoiseController::class, 'listFiles']);
    Route::delete('/files', [WhiteNoiseController::class, 'deleteFile']);
    
    // Information
    Route::get('/types', [WhiteNoiseController::class, 'getNoiseTypes']);
    Route::get('/health', [WhiteNoiseController::class, 'healthCheck']);
});