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