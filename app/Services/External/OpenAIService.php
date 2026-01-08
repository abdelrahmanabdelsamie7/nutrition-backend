<?php

namespace App\Services\External;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OpenAIService
{
    private Client $client;
    private string $apiKey;
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.model', 'gpt-4-turbo-preview');
        $this->baseUrl = config('services.openai.base_url', 'https://api.openai.com/v1/');

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ],
        ]);
    }

    /**
     * Get completion from OpenAI
     */
    public function getCompletion(string $prompt, array $parameters = []): string
    {
        if (empty($this->apiKey)) {
            throw new \Exception('OpenAI API key not configured');
        }

        $cacheKey = 'openai_cache:' . md5($prompt . json_encode($parameters));

        // Check cache first
        if (Cache::has($cacheKey)) {
            Log::info('Using cached OpenAI response');
            return Cache::get($cacheKey);
        }

        try {
            Log::info('Calling OpenAI API', [
                'model' => $this->model,
                'prompt_length' => strlen($prompt),
            ]);

            $response = $this->client->post('chat/completions', [
                'json' => [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => $parameters['temperature'] ?? 0.7,
                    'max_tokens' => $parameters['max_tokens'] ?? 1000,
                    'top_p' => $parameters['top_p'] ?? 0.95,
                ],
                'http_errors' => false,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $data = json_decode($responseBody, true);

            Log::debug('OpenAI API Response', [
                'status' => $statusCode,
                'response_keys' => array_keys($data),
            ]);

            if ($statusCode !== 200) {
                Log::error('OpenAI API error', [
                    'status' => $statusCode,
                    'error' => $data['error']['message'] ?? 'Unknown error',
                ]);

                throw new \Exception(
                    'OpenAI API error: ' . ($data['error']['message'] ?? "Status: {$statusCode}")
                );
            }

            // Extract text from response
            $text = $this->extractTextFromResponse($data);

            if (empty($text)) {
                Log::error('Empty response from OpenAI API', ['response' => $data]);
                throw new \Exception('Empty response from OpenAI API');
            }

            // Cache for 6 hours
            Cache::put($cacheKey, $text, now()->addHours(6));

            Log::info('OpenAI API response received', [
                'response_length' => strlen($text),
            ]);

            return $text;
        } catch (RequestException $e) {
            Log::error('OpenAI API request failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            throw new \Exception('OpenAI API request failed: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Unexpected error in OpenAI service', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Extract text from OpenAI response
     */
    private function extractTextFromResponse(array $response): string
    {
        try {
            if (isset($response['choices'][0]['message']['content'])) {
                return trim($response['choices'][0]['message']['content']);
            }

            if (isset($response['choices'][0]['text'])) {
                return trim($response['choices'][0]['text']);
            }

            return '';
        } catch (\Exception $e) {
            Log::error('Failed to extract text from OpenAI response', [
                'error' => $e->getMessage(),
                'response_structure' => array_keys($response),
            ]);

            return '';
        }
    }

    /**
     * Get structured recommendations from OpenAI
     */
    public function getStructuredRecommendations(string $prompt): array
    {
        try {
            $response = $this->getCompletion($prompt);

            // Try to parse as JSON
            $data = json_decode($response, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }

            // Try to extract JSON
            preg_match('/\{.*\}/s', $response, $matches);
            if (isset($matches[0])) {
                $data = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $data;
                }
            }

            // Return as text
            return [
                'summary' => 'AI Analysis',
                'recommendations' => ['Review the following suggestions:'],
                'raw_response' => $response,
                'parse_error' => 'Could not parse as JSON'
            ];
        } catch (\Exception $e) {
            Log::error('OpenAI structured recommendations failed', [
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Test OpenAI API connection
     */
    public function testConnection(): array
    {
        try {
            $testPrompt = 'Respond with "OpenAI API is working" in JSON format: {"status": "ok", "message": "API is working"}';

            $response = $this->getCompletion($testPrompt, [
                'max_tokens' => 100,
                'temperature' => 0.1,
            ]);

            $data = json_decode($response, true);

            return [
                'status' => 'connected',
                'response' => $data,
                'message' => 'OpenAI API is responding normally',
                'model' => $this->model,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'disconnected',
                'error' => $e->getMessage(),
                'api_key_set' => !empty($this->apiKey),
                'message' => 'OpenAI API connection failed',
            ];
        }
    }

    /**
     * Get available models (simplified)
     */
    public function getAvailableModels(): array
    {
        return [
            'gpt-4-turbo-preview' => 'GPT-4 Turbo Preview',
            'gpt-4' => 'GPT-4',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
        ];
    }
}