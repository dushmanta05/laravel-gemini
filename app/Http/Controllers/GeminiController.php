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
use Throwable;

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

    /**
     * @OA\Post(
     *     path="/api/gemini/generate",
     *     summary="Generate a response using Gemini",
     *     tags={"Gemini"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"message"},
     *             @OA\Property(property="message", type="string", example="Hello, how are you?")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="response", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Message is required")
     *         )
     *     )
     * )
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

    /**
     * @OA\Post(
     *     path="/api/gemini/generate-structured",
     *     summary="Generate a structured response using Gemini",
     *     tags={"Gemini"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"message", "schema_type"},
     *             @OA\Property(property="message", type="string", example="Explain quantum physics"),
     *             @OA\Property(property="schema_type", type="string", example="educational")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="response", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
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

    /**
     * @OA\Post(
     *     path="/api/gemini/generate-with-image",
     *     summary="Generate response with image analysis",
     *     tags={"Gemini"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"message", "image"},
     *                 @OA\Property(property="message", type="string", example="What's in this image?"),
     *                 @OA\Property(property="image", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="response", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
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

    /**
     * @OA\Post(
     *     path="/api/gemini/analyze-with-file",
     *     summary="Analyze uploaded file (PDF/MP4)",
     *     tags={"Gemini"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"message", "file"},
     *                 @OA\Property(property="message", type="string", example="Analyze this document"),
     *                 @OA\Property(property="file", type="string", format="binary", description="PDF or MP4 file")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful analysis",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="response", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
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

    /**
     * Analyze an uploaded MP4 video file with a given message prompt.
     *
     * @param Request $request
     * @return JsonResponse
     */

    /**
     * @OA\Post(
     *     path="/api/gemini/analyze-video",
     *     summary="Analyze MP4 video file",
     *     tags={"Gemini"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"message", "video"},
     *                 @OA\Property(property="message", type="string", maxLength=255, example="Describe this video"),
     *                 @OA\Property(property="video", type="string", format="binary", description="MP4 video file (max 10MB)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful video analysis",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="object")
     *         )
     *     )
     * )
     */
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

    /**
     * Generate a chat response using previous chat history if provided.
     *
     * @param Request $request
     * @return JsonResponse
     */

    /**
     * @OA\Post(
     *     path="/api/gemini/chat",
     *     summary="Generate chat response with history",
     *     tags={"Chat"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"message"},
     *             @OA\Property(property="message", type="string", example="Hello, how are you?"),
     *             @OA\Property(property="chat_id", type="string", format="uuid", nullable=true, example="550e8400-e29b-41d4-a716-446655440000")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful chat response",
     *         @OA\JsonContent(
     *             @OA\Property(property="chat_id", type="string", format="uuid"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="response", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Chat not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Chat not found")
     *         )
     *     )
     * )
     */
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


    /**
     * @OA\Post(
     *     path="/api/gemini/stream",
     *     summary="Stream content from Gemini in real-time",
     *     description="Returns server-sent events stream. Best tested with cURL or EventSource, not typical API clients. Example: curl -N -X POST http://127.0.0.1:8000/api/gemini/stream -H 'Content-Type: application/json' -d '{""message"": ""Write a story on Gemini.""}' The -N flag disables buffering for real-time streaming.",
     *     tags={"Gemini"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"message"},
     *             @OA\Property(property="message", type="string", example="Tell me a story")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Server-sent events stream (use cURL or EventSource)",
     *         @OA\MediaType(
     *             mediaType="text/event-stream"
     *         )
     *     )
     * )
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

    /**
     * Handle a function call prompt using Gemini's capabilities.
     *
     * @param Request $request
     * @return JsonResponse
     */

    /**
     * @OA\Post(
     *     path="/api/gemini/function-call",
     *     summary="Handle function call with Gemini",
     *     tags={"Gemini"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"message"},
     *             @OA\Property(property="message", type="string", example="Call the weather function for New York")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Function call response",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="response", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
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

    /**
     * Count the number of tokens in the provided message prompt.
     *
     * @param Request $request
     * @return JsonResponse
     */

    /**
     * @OA\Post(
     *     path="/api/gemini/count-tokens",
     *     summary="Count tokens in a message",
     *     tags={"Utilities"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"message"},
     *             @OA\Property(property="message", type="string", example="Hello world, how are you doing today?")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token count",
     *         @OA\JsonContent(
     *             @OA\Property(property="tokens", type="integer", example=8)
     *         )
     *     )
     * )
     */
    public function countTokensInPrompt(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $tokensCount = $this->geminiService->countTokens($request->input('message'));

        return response()->json(['tokens' => $tokensCount]);
    }

    /**
     * Generate a Gemini response with custom configuration settings.
     *
     * @param Request $request
     * @param GeminiService $geminiService
     * @return JsonResponse
     */

    /**
     * @OA\Post(
     *     path="/api/gemini/generate-with-config",
     *     summary="Generate response with custom configuration",
     *     tags={"Gemini"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"message"},
     *             @OA\Property(property="message", type="string", example="Generate creative content")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response with config",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="output", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function generateWithConfig(Request $request, GeminiService $geminiService)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $message = $request->input('message');

        try {
            $response = $geminiService->generateWithConfig($message);

            return response()->json([
                'success' => true,
                'output' => $response,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
