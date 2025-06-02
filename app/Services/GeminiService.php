<?php

namespace App\Services;

use App\Gemini\GeminiConfig;
use App\Gemini\ResponseSchema;
use Exception;
use Gemini;
use Gemini\Data\Content;
use Gemini\Data\FunctionDeclaration;
use Gemini\Data\FunctionResponse;
use Gemini\Data\GenerationConfig;
use Gemini\Data\Part;
use Gemini\Data\Tool;
use Gemini\Enums\FileState;
use Gemini\Enums\ResponseMimeType;
use Gemini\Data\Schema;
use Gemini\Data\Blob;
use Gemini\Enums\MimeType;
use Gemini\Enums\Role;
use Generator;
use Illuminate\Http\UploadedFile;
use Gemini\Data\UploadedFile as GeminiUploadedFile;
use Illuminate\Support\Collection;
use RuntimeException;

class GeminiService
{
    private $model;
    private $apiKey;
    private $client;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model');
        $this->client = Gemini::client($this->apiKey);
    }

    /**
     * Generate content using the specified model.
     *
     * @param string $prompt The input prompt for content generation.
     * @return string|null The generated content or null on failure.
     */
    public function generateContent(string $prompt): ?string
    {
        try {
            $result = $this->client->generativeModel(model: $this->model)->generateContent($prompt);
            return $result->text();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Generate structured content using Gemini based on a defined response schema.
     *
     * @param string $prompt The input prompt for content generation.
     * @param Schema $schema The expected schema that defines the response structure.
     * @return array|null The structured response as an associative array, or null on failure.
     */
    public function generateStructuredContent(string $prompt, Schema $schema): ?array
    {
        try {
            $result = $this->client
                ->generativeModel(model: $this->model)
                ->withGenerationConfig(new GenerationConfig(
                    responseMimeType: ResponseMimeType::APPLICATION_JSON,
                    responseSchema: $schema
                ))
                ->generateContent($prompt);

            return json_decode(json_encode($result->json()), true);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Generate content using both text and image input.
     *
     * @param string $prompt Text prompt describing the request.
     * @param UploadedFile $image_file The uploaded image file.
     * @return string|null The generated text response or null on failure.
     *
     * @throws Exception If image processing or generation fails.
     */
    public function generateContentWithImage(string $prompt, UploadedFile $image_file): ?string
    {
        try {
            $imageData = file_get_contents($image_file->getRealPath());

            $mimeType = match ($image_file->getMimeType()) {
                'image/jpeg' => MimeType::IMAGE_JPEG,
                'image/png' => MimeType::IMAGE_PNG,
                'image/webp' => MimeType::IMAGE_WEBP,
                default => null,
            };

            if (!$mimeType) {
                throw new Exception('Unsupported image MIME type');
            }

            $blob = new Blob(
                mimeType: $mimeType,
                data: base64_encode($imageData)
            );

            $result = $this->client
                ->generativeModel(model: $this->model)
                ->generateContent([
                    $prompt,
                    $blob
                ]);

            return $result->text();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Uploads a file (PDF or MP4 video) to Gemini storage.
     *
     * @param UploadedFile $file The uploaded file instance from the request.
     * @return GeminiUploadedFile A Gemini UploadedFile instance with file URI and MIME type.
     * @throws Exception If the MIME type is unsupported or file processing fails.
     */
    public function uploadFileToGeminiStorage(UploadedFile $file): GeminiUploadedFile
    {
        $mimeType = match ($file->getMimeType()) {
            'application/pdf' => MimeType::APPLICATION_PDF,
            'video/mp4' => MimeType::VIDEO_MP4,
            default => throw new Exception('Unsupported MIME type: ' . $file->getMimeType()),
        };

        $meta = $this->client->files()->upload(
            filename: $file->getRealPath(),
            mimeType: $mimeType,
            displayName: $file->getClientOriginalName()
        );

        do {
            sleep(2);
            $meta = $this->client->files()->metadataGet($meta->uri);
        } while (!$meta->state->complete());

        if ($meta->state === FileState::Failed) {
            throw new Exception('File processing failed for ' . $file->getClientOriginalName());
        }

        return new GeminiUploadedFile(
            fileUri: $meta->uri,
            mimeType: $mimeType
        );
    }

    /**
     * Upload a file and analyze it using the given prompt.
     *
     * @param UploadedFile $file
     * @param string $prompt
     * @return string|null
     */
    public function analyzeUploadedFile(UploadedFile $file, string $prompt): ?string
    {
        $uploaded_file = $this->uploadFileToGeminiStorage($file);

        $result = $this->client
            ->generativeModel(model: $this->model)
            ->generateContent([
                $prompt,
                $uploaded_file
            ]);

        return $result->text();
    }

    /**
     * Upload a file and analyze it using the given prompt.
     *
     * @param UploadedFile $file The uploaded file (PDF or MP4).
     * @param string $prompt The prompt to guide content generation.
     * @return string|null The generated content or null on failure.
     *
     * @throws Exception If the upload or generation fails.
     */
    public function analyzeUploadedVideo(UploadedFile $file, string $prompt): ?string
    {
        $uploadedFile = $this->uploadFileToGeminiStorage($file);

        $result = $this->client
            ->generativeModel(model: $this->model)
            ->generateContent([
                $prompt,
                $uploadedFile
            ]);

        return $result->text();
    }

    /**
     * Build Gemini chat history from previous messages
     *
     * @param Collection $messages
     * @return array
     */
    private function buildChatHistory(Collection $messages): array
    {
        return $messages->flatMap(fn($msg) => [
            Content::parse($msg->user, role: Role::USER),
            Content::parse($msg->model, role: Role::MODEL),
        ])->toArray();
    }

    /**
     * Start a chat session with memory and return the AI's reply
     *
     * @param string $message
     * @param Collection $historyMessages
     * @return string
     */
    public function getResponseWithHistory(string $message, Collection $historyMessages): string
    {
        $history = $this->buildChatHistory($historyMessages);

        try {
            $chat = $this->client
                ->generativeModel($this->model)
                ->startChat(history: $history);

            $response = $chat->sendMessage($message);

            return $response->text();
        } catch (Exception $e) {
            throw new RuntimeException('An unexpected error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Stream content generation from Gemini for a given prompt.
     *
     * @param string $prompt The user prompt to send to the model.
     * @return Generator A generator yielding streamed text chunks from the model.
     *
     * @throws RuntimeException If an error occurs while streaming.
     */
    public function streamGenerateContent(string $prompt): Generator
    {
        try {
            $stream = $this->client
                ->generativeModel($this->model)
                ->streamGenerateContent($prompt);

            foreach ($stream as $chunk) {
                yield $chunk->text();
            }
        } catch (Exception $e) {
            throw new RuntimeException('Streaming failed: ' . $e->getMessage());
        }
    }

    /**
     * Simulate a function call (e.g. multiply) using Gemini tools and structured prompts.
     *
     * @param string $prompt The user's prompt invoking the function.
     * @return string The Gemini model's response after the function execution.
     *
     * @throws RuntimeException If the function call fails.
     */
    public function handleFunctionCall(string $prompt): string
    {
        $schema = ResponseSchema::get('multiply');

        $tool = new Tool(
            functionDeclarations: [
                new FunctionDeclaration(
                    name: 'multiply',
                    description: 'Multiplies two numbers',
                    parameters: $schema
                )
            ]
        );

        $chat = $this->client
            ->generativeModel($this->model)
            ->withTool($tool)
            ->startChat();

        $response = $chat->sendMessage($prompt);

        if ($response->parts()[0]->functionCall !== null) {
            $functionCall = $response->parts()[0]->functionCall;

            if ($functionCall->name === 'multiply') {
                $a = $functionCall->args['a'];
                $b = $functionCall->args['b'];

                $functionResponse = new Content(
                    parts: [
                        new Part(
                            functionResponse: new FunctionResponse(
                                name: 'multiply',
                                response: ['result' => $a * $b],
                            )
                        )
                    ],
                    role: Role::USER
                );

                $response = $chat->sendMessage($functionResponse);
            }
        }

        return $response->text();
    }

    /**
     * Count tokens for a given prompt string.
     *
     * @param string $prompt
     * @return int Total number of tokens in the prompt
     * @throws RuntimeException on failure
     */
    public function countTokens(string $prompt): int
    {
        try {
            $response = $this->client
                ->generativeModel($this->model)
                ->countTokens($prompt);

            return $response->totalTokens;
        } catch (Exception $e) {
            throw new RuntimeException('Failed to count tokens: ' . $e->getMessage());
        }
    }

    /**
     * Generate content using Gemini with predefined safety settings and generation config.
     *
     * @param string $prompt The user prompt to process.
     * @return string The generated content.
     *
     * @throws RuntimeException If content generation fails.
     */
    public function generateWithConfig(string $prompt): string
    {
        $model = $this->client
            ->generativeModel($this->model)
            ->withSafetySetting(GeminiConfig::getSafetySettingDangerousContent())
            ->withSafetySetting(GeminiConfig::getSafetySettingHateSpeech())
            ->withGenerationConfig(GeminiConfig::getGenerationConfig());

        $response = $model->generateContent($prompt);

        return $response->text();
    }
}
