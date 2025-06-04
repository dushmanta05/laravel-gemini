# Laravel Gemini

Demo Laravel project showcasing various ways to integrate Google’s Gemini AI models using a dedicated service class. It supports generating text, analyzing images, videos, and files, streaming responses, function calling, structured responses, and token counting.

## Features Implemented

-   Generate text responses using Gemini

-   Generate structured responses with custom schemas

-   Send image + prompt to Gemini and receive analysis

-   Analyze PDF and MP4 file content via prompts

-   Summarize video content into text

-   Use chat history for contextual responses

-   Stream real-time responses (SSE)

-   Call AI functions with dynamic interpretation

-   Count tokens in a given prompt

-   Customize configuration for the Gemini API

## Setup & Installation

1.  Clone the repository

    ```bash
    git clone https://github.com/dushmanta05/laravel-gemini.git
    ```

2.  Navigate into the project directory

    ```
    cd laravel-gemini
    ```

3.  Install dependencies

    ```
    composer install
    ```

4.  Copy environment configuration file

    ```
    cp .env.example .env
    ```

5.  Generate the application key

    ```
    php artisan key:generate
    ```

6.  Set your Gemini API key and model in `.env`

    ```
    GEMINI_API_KEY=your_api_key_here
    GEMINI_MODEL=gemini-2.0-flash
    ```

7.  Run migrations (only required for Multi-turn Conversations feature)

    ```
    php artisan migrate
    ```

## API & Usage

This project includes a centralized GeminiController with multiple routes and methods for:

-   **Prompting** - Basic text generation with Gemini

-   **Streaming** - Real-time response streaming with Server-Sent Events

-   **File/video/image analysis** - Upload and analyze PDF, MP4, and image files

-   **Function calling** - Dynamic AI function execution with structured parameters

-   **Token counting** - Calculate token usage for prompts

-   **Structured outputs** - Generate responses following predefined JSON schemas

-   **Multi-turn conversations** - Chat sessions with persistent history and context

-   **File upload to Gemini storage** - Secure file handling for large media analysis

-   **Custom configuration** - Advanced safety settings and generation parameters
