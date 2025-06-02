<?php

namespace App\Http\Controllers;

use App\Gemini\ResponseSchema;
use Exception;
use Illuminate\Http\Request;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Response;
use App\Models\Chat;
use App\Models\ChatMessage;

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
     * @return JsonResponse
     */

    public function generateResponse(Request $request): JsonResponse
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
     * @return JsonResponse
     */

    public function generateStructuredResponse(Request $request): JsonResponse
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
        } catch (Exception $e) {
            return response()->json([
                'message' => $message,
                'response' => ['error' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Generate a response from Gemini using an uploaded image and a text prompt.
     *
     * @param Request $request
     * @return JsonResponse
     */

    public function generateResponseWithImage(Request $request): JsonResponse
    {
        $prompt = $request->input(key: 'message');
        $image_file = $request->file(key: 'image');

        if (!$prompt || !$image_file) {
            return response()->json(data: [
                'error' => 'Both message and image are required.'
            ], status: 400);
        }

        try {
            $response = $this->geminiService->generateContentWithImage($prompt, image_file: $image_file);

            return response()->json(data: [
                'message' => $prompt,
                'response' => $response
            ]);
        } catch (Exception $e) {
            return response()->json(data: [
                'error' => 'Failed to process image: ' . $e->getMessage()
            ], status: 500);
        }
    }

    /**
     * Handle file upload (PDF/MP4) and send prompt to Gemini for analysis.
     *
     * @param Request $request
     * @return JsonResponse
     */

    public function analyzeUploadedFile(Request $request): JsonResponse
    {
        $prompt = $request->input('message');
        $file = $request->file('file');

        if (!$prompt || !$file) {
            return response()->json([
                'error' => 'Both message and file are required.'
            ], 400);
        }

        try {
            $response = $this->geminiService->analyzeUploadedFile($file, $prompt);

            return response()->json([
                'message' => $prompt,
                'response' => $response
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to analyze file: ' . $e->getMessage()
            ], 500);
        }
    }

    public function analyzeVideoFile(Request $request): JsonResponse
    {
        $request->validate([
            'video' => 'required|file|mimetypes:video/mp4|max:10240',
            'message' => 'required|string|max:255',
        ], [
            'video.mimetypes' => 'Only MP4 video files are supported.',
            'video.max' => 'The video must be less than 10MB.',
        ]);

        try {
            $videoFile = $request->file('video');
            $message = $request->input('message');

            $responseText = $this->geminiService->analyzeUploadedVideo($videoFile, $message);

            return response()->json([
                'message' => $message,
                'description' => $responseText,
            ]);
        } catch (ValidationException $ve) {
            return response()->json(['error' => $ve->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function generateChatResponse(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
            'chat_id' => 'nullable|uuid'
        ]);

        $message = $request->input('message');
        $chatId = $request->input('chat_id');

        $chat = $chatId ? Chat::find($chatId) : Chat::create();

        if (!$chat) {
            return response()->json(['error' => 'Chat not found'], 404);
        }

        $previousMessages = ChatMessage::where('chat_id', $chat->id)
            ->orderBy('created_at')
            ->get();

        $responseText = $this->geminiService->getResponseWithHistory($message, $previousMessages);

        if (!$responseText) {
            return response()->json(['error' => 'Failed to get a valid response from Gemini'], 500);
        }

        ChatMessage::create([
            'chat_id' => $chat->id,
            'user' => $message,
            'model' => $responseText,
        ]);

        return response()->json([
            'chat_id' => $chat->id,
            'message' => $message,
            'response' => $responseText
        ]);
    }

    /**
     * Stream content from Gemini in real-time based on the given prompt.
     *
     * @param Request $request
     * @return Response
     */
    public function streamResponse(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $prompt = $request->input('message');

        $generator = $this->geminiService->streamGenerateContent($prompt);

        return response()->stream(function () use ($generator) {
            ob_implicit_flush(true);

            foreach ($generator as $chunk) {
                echo $chunk;
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function callFunction(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        try {
            $response = $this->geminiService->handleFunctionCall($request->input('message'));

            return response()->json([
                'message' => $request->input('message'),
                'response' => $response,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
