<?php

namespace App\Gemini;

use Gemini\Data\Schema;
use Gemini\Enums\DataType;

class ResponseSchema
{
    public static function get(string $type): ?Schema
    {
        return match ($type) {
            'recipe' => self::recipeSchema(),
            'details' => self::detailsSchema(),
            'multiply' => self::multiplySchema(),
            default => null,
        };
    }

    private static function recipeSchema(): Schema
    {
        return new Schema(
            type: DataType::OBJECT,
            properties: [
                'response_message' => new Schema(type: DataType::STRING),
                'title' => new Schema(type: DataType::STRING),
                'steps' => new Schema(
                    type: DataType::ARRAY ,
                    items: new Schema(
                        type: DataType::OBJECT,
                        properties: [
                            'step_number' => new Schema(type: DataType::INTEGER),
                            'instruction' => new Schema(type: DataType::STRING),
                        ],
                        required: ['step_number', 'instruction']
                    )
                ),
            ],
            required: ['response_message', 'title', 'steps']
        );
    }

    private static function detailsSchema(): Schema
    {
        return new Schema(
            type: DataType::OBJECT,
            properties: [
                'response_message' => new Schema(type: DataType::STRING),
                'title' => new Schema(type: DataType::STRING),
                'description' => new Schema(type: DataType::STRING),
            ],
            required: ['response_message', 'title', 'description']
        );
    }

    private static function multiplySchema(): Schema
    {
        return new Schema(
            type: DataType::OBJECT,
            properties: [
                'a' => new Schema(
                    type: DataType::NUMBER,
                    description: 'First number'
                ),
                'b' => new Schema(
                    type: DataType::NUMBER,
                    description: 'Second number'
                ),
            ],
            required: ['a', 'b']
        );
    }
}
