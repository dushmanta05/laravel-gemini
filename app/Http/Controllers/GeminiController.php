<?php

namespace App\Http\Controllers;

use App\Gemini\ResponseSchema;
use Illuminate\Http\Request;
use App\Services\GeminiService;

class GeminiController extends Controller
{
    private $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    /**
     * Generate a response using the GeminiService.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function generateResponse(Request $request)
    {
        $message = $request->input('message');

        if (!$message) {
            return response()->json(['error' => 'Message is required'], 400);
        }

        $response = $this->geminiService->generateContent($message);

        return response()->json([
            'message' => $message,
            'response' => $response
        ]);
    }

    /**
     * Generate a structured response using the GeminiService and a specified schema.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function generateStructuredResponse(Request $request)
    {
        $message = $request->input('message');
        $schemaType = $request->input('schema_type');

        if (!$message || !$schemaType) {
            return response()->json([
                'error' => 'Message and schema_type are required'
            ], 400);
        }

        try {
            $schema = ResponseSchema::get($schemaType);
            if (!$schema) {
                return response()->json(['error' => "Invalid schema type: $schemaType"], 400);
            }

            $response = $this->geminiService->generateStructuredContent($message, $schema);

            return response()->json([
                'message' => $message,
                'response' => $response
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $message,
                'response' => ['error' => $e->getMessage()]
            ], 500);
        }
    }
}
