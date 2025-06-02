<?php

namespace App\Gemini;

use Gemini\Data\GenerationConfig;
use Gemini\Data\SafetySetting;
use Gemini\Enums\HarmCategory;
use Gemini\Enums\HarmBlockThreshold;

class GeminiConfig
{
    public static function getSafetySettingDangerousContent(): SafetySetting
    {
        return new SafetySetting(
            category: HarmCategory::HARM_CATEGORY_DANGEROUS_CONTENT,
            threshold: HarmBlockThreshold::BLOCK_ONLY_HIGH
        );
    }

    public static function getSafetySettingHateSpeech(): SafetySetting
    {
        return new SafetySetting(
            category: HarmCategory::HARM_CATEGORY_HATE_SPEECH,
            threshold: HarmBlockThreshold::BLOCK_ONLY_HIGH
        );
    }

    public static function getGenerationConfig(): GenerationConfig
    {
        return new GenerationConfig(
            stopSequences: ['Title'],
            maxOutputTokens: 800,
            temperature: 1.0,
            topP: 0.8,
            topK: 10
        );
    }
}
