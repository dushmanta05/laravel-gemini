# Laravel Gemini

Demo Laravel project showcasing various ways to integrate **Google's Gemini AI** models using the [Gemini PHP](https://github.com/google-gemini-php/client) package. In this demo, I've illustrated implementations for generating text, analyzing images, videos, and files, streaming responses, function calling, structured responses, and token counting.

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

## Acknowledgments

Special thanks to the authors of the Laravel Gemini Client package:

-   [Fatih AYDIN](https://github.com/aydinfatih)
-   [Vytautas Smilingis](https://github.com/plytas)

Their excellent work made this integration seamless and efficient.

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

    ```env
    GEMINI_API_KEY=your_api_key_here
    GEMINI_MODEL=gemini-2.0-flash
    ```

7.  Run migrations (only required for Multi-turn Conversations feature)

    ```
    php artisan migrate
    ```

8.  Start the development server

    ```
    php artisan serve
    ```

## API & Usage

This project includes a centralized _GeminiController_ with multiple routes and methods for:

-   **Prompting** - Basic text generation with Gemini

-   **Streaming** - Real-time response streaming with Server-Sent Events

-   **File/video/image analysis** - Upload and analyze PDF, MP4, and image files

-   **Function calling** - Dynamic AI function execution with structured parameters

-   **Token counting** - Calculate token usage for prompts

-   **Structured outputs** - Generate responses following predefined JSON schemas

-   **Multi-turn conversations** - Chat sessions with persistent history and context

-   **File upload to Gemini storage** - Secure file handling for large media analysis

-   **Custom configuration** - Advanced safety settings and generation parameters

## API Test Collection

This project includes interactive _Swagger/OpenAPI_ documentation for all endpoints.

1. Configure the API base URL in your `.env` file:

    ```env
    L5_SWAGGER_HOST=127.0.0.1:8000
    ```

2. Generate the API documentation:

    ```bash
    php artisan l5-swagger:generate
    ```

3. Start the development server (if not already running):

    ```
    php artisan serve
    ```

4. Access the interactive API documentation at:

    ```
    http://127.0.0.1:8000/api/documentation
    ```

Note: For streaming endpoints (like `/api/gemini/stream`), use **cURL** for testing as they return Server-Sent Events which aren't well supported by typical API clients:

```bash
curl -N -X POST http://127.0.0.1:8000/api/gemini/stream \
  -H "Content-Type: application/json" \
  -d '{"message": "Write a story on Gemini."}'
```
