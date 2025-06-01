<?php

namespace App\Services;

use Gemini;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\ResponseMimeType;
use Gemini\Data\Schema;

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
     * @param string $model The model to use for content generation. Default is 'gemini-2.0-flash'.
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
        } catch (\Exception $e) {
            return null;
        }
    }
}
