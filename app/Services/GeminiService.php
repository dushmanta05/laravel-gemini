<?php

namespace App\Services;

use Exception;
use Gemini;
use Gemini\Data\Content;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\FileState;
use Gemini\Enums\ResponseMimeType;
use Gemini\Data\Schema;
use Gemini\Data\Blob;
use Gemini\Enums\MimeType;
use Gemini\Enums\Role;
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
        } catch (\Exception $e) {
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
     * @param string $prompt Text prompt describing what you want.
     * @param string $imageUrl URL or local path to the image file.
     * @return string|null The generated response text or null on failure.
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
     * @throws \Exception If the MIME type is unsupported or file processing fails.
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
     * Uploads a video file and generates a description using the Gemini model.
     *
     * @param UploadedFile $file The uploaded MP4 video file.
     * @param string $prompt The prompt describing what you want to ask about the video.
     * 
     * @return string|null The generated AI description or null on failure.
     *
     * @throws \Exception If the file is not MP4 or upload fails.
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
}
