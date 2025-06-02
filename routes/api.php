<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GeminiController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/gemini/generate', [GeminiController::class, 'generateResponse']);
Route::post('/gemini/generate-structured', [GeminiController::class, 'generateStructuredResponse']);
Route::post('/gemini/generate-with-image', [GeminiController::class, 'generateResponseWithImage']);
Route::post('/gemini/analyze-with-file', [GeminiController::class, 'analyzeUploadedFile']);
Route::post('/gemini/analyze-video', [GeminiController::class, 'analyzeVideoFile']);
Route::post('/gemini/chat', [GeminiController::class, 'generateChatResponse']);
Route::post('/gemini/stream', [GeminiController::class, 'streamResponse']);
Route::post('/gemini/function-call', [GeminiController::class, 'callFunction']);
Route::post('/gemini/count-tokens', [GeminiController::class, 'countTokensInPrompt']);
Route::post('/gemini/generate-with-config', [GeminiController::class, 'generateWithConfig']);