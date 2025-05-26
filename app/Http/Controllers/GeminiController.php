<?php

namespace App\Http\Controllers;

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

}
