<?php

namespace App\Services\External;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Exceptions\ExternalServiceException;
use Exception;

class CalorieNinjasService
{
    private Client $client;
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->apiKey = config('services.calorie_ninjas.api_key');
        $this->baseUrl = config('services.calorie_ninjas.base_url', 'https://api.calorieninjas.com/v1/');
        $this->timeout = config('services.calorie_ninjas.timeout', 10);

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeout,
            'headers' => [
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'verify' => false, // Temporarily for debugging
            'http_errors' => false,
        ]);
    }

    /**
     * Get nutrition data for food query
     */
    public function getNutritionData(string $query): array
    {
        // Clean and validate query
        $cleanQuery = $this->cleanFoodQuery($query);

        if (empty($cleanQuery)) {
            Log::warning('Empty query after cleaning', ['original' => $query]);
            return $this->getFallbackNutritionData($query);
        }

        $cacheKey = $this->generateCacheKey($cleanQuery);

        // Check cache first
        if (Cache::has($cacheKey)) {
            Log::info('Cache hit for food query', ['query' => $cleanQuery]);
            return Cache::get($cacheKey);
        }

        try {
            Log::info('Calling CalorieNinjas API', [
                'query' => $cleanQuery,
                'api_key_exists' => !empty($this->apiKey),
                'api_key_first_chars' => substr($this->apiKey, 0, 5) . '...'
            ]);

            // Make GET request
            $response = $this->client->get('nutrition', [
                'query' => ['query' => $cleanQuery],
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();

            Log::debug('CalorieNinjas Raw Response', [
                'status' => $statusCode,
                'headers' => $response->getHeaders(),
                'body_preview' => substr($responseBody, 0, 500)
            ]);

            // Parse JSON response
            $body = json_decode($responseBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON response from CalorieNinjas', [
                    'json_error' => json_last_error_msg(),
                    'raw_response' => $responseBody,
                    'query' => $cleanQuery
                ]);

                return $this->getFallbackNutritionData($cleanQuery);
            }

            Log::info('CalorieNinjas API Response', [
                'status' => $statusCode,
                'query' => $cleanQuery,
                'response_keys' => is_array($body) ? array_keys($body) : 'not_an_array',
                'has_items' => isset($body['items']) && is_array($body['items'])
            ]);

            if ($statusCode !== 200) {
                Log::error('CalorieNinjas API error', [
                    'status' => $statusCode,
                    'body' => $body,
                    'query' => $cleanQuery
                ]);

                throw new Exception(
                    "CalorieNinjas API error: {$statusCode}",
                    $statusCode,
                    $body
                );
            }

            if (!isset($body['items']) || !is_array($body['items']) || empty($body['items'])) {
                Log::warning('No nutrition data found for query', [
                    'query' => $cleanQuery,
                    'response' => $body
                ]);

                return $this->getFallbackNutritionData($cleanQuery);
            }

            $normalizedData = $this->normalizeResponse($body['items'], $cleanQuery);

            // Cache for 24 hours
            Cache::put($cacheKey, $normalizedData, now()->addHours(24));

            Log::info('Nutrition data retrieved successfully', [
                'query' => $cleanQuery,
                'calories' => $normalizedData['calories'],
                'items_count' => count($body['items'])
            ]);

            return $normalizedData;
        } catch (RequestException $e) {
            Log::error('CalorieNinjas API request failed', [
                'query' => $cleanQuery,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'request' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'No response'
            ]);

            return $this->getFallbackNutritionData($cleanQuery);
        } catch (\Exception $e) {
            Log::error('Unexpected error in CalorieNinjas service', [
                'query' => $cleanQuery,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->getFallbackNutritionData($cleanQuery);
        }
    }

    /**
     * Clean food query
     */
    private function cleanFoodQuery(string $query): string
    {
        if (empty($query)) {
            return 'apple';
        }

        // Remove extra spaces
        $query = trim(preg_replace('/\s+/', ' ', $query));

        // Keep only safe characters
        $query = preg_replace('/[^\w\s\d.,%]/u', '', $query);

        // Limit to 1500 characters (API limit)
        if (strlen($query) > 1500) {
            $query = substr($query, 0, 1500);
        }

        return $query;
    }

    /**
     * Normalize API response
     */
    private function normalizeResponse(array $items, string $originalQuery): array
    {
        $total = [
            'calories' => 0,
            'protein' => 0,
            'carbs' => 0,
            'fat' => 0,
            'fiber' => 0,
            'sugar' => 0,
            'sodium' => 0,
            'serving_size_g' => 0,
            'items' => [],
            'query' => $originalQuery,
            'items_count' => count($items),
            'source' => 'calorieninjas',
        ];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $total['calories'] += $item['calories'] ?? 0;
            $total['protein'] += $item['protein_g'] ?? 0;
            $total['carbs'] += $item['carbohydrates_total_g'] ?? 0;
            $total['fat'] += $item['fat_total_g'] ?? 0;
            $total['fiber'] += $item['fiber_g'] ?? 0;
            $total['sugar'] += $item['sugar_g'] ?? 0;
            $total['sodium'] += $item['sodium_mg'] ?? 0;
            $total['serving_size_g'] += $item['serving_size_g'] ?? 0;

            $total['items'][] = [
                'name' => $item['name'] ?? 'Unknown',
                'calories' => $item['calories'] ?? 0,
                'protein_g' => $item['protein_g'] ?? 0,
                'carbs_g' => $item['carbohydrates_total_g'] ?? 0,
                'fat_g' => $item['fat_total_g'] ?? 0,
                'serving_size_g' => $item['serving_size_g'] ?? 0,
            ];
        }

        // Round values
        $total['calories'] = round($total['calories'], 2);
        $total['protein'] = round($total['protein'], 2);
        $total['carbs'] = round($total['carbs'], 2);
        $total['fat'] = round($total['fat'], 2);
        $total['fiber'] = round($total['fiber'], 2);
        $total['sugar'] = round($total['sugar'], 2);
        $total['sodium'] = round($total['sodium'], 2);

        return $total;
    }

    /**
     * Fallback nutrition data
     */
    private function getFallbackNutritionData(string $query): array
    {
        Log::warning('Using fallback nutrition data', ['query' => $query]);

        // Parse quantity from query
        $quantity = 1;
        if (preg_match('/(\d+)/', strtolower($query), $matches)) {
            $quantity = (int)$matches[1];
        }

        // Common foods database (per 100g)
        $foodDatabase = [
            'banana' => ['calories' => 89, 'protein' => 1.1, 'carbs' => 23, 'fat' => 0.3],
            'apple' => ['calories' => 52, 'protein' => 0.3, 'carbs' => 14, 'fat' => 0.2],
            'egg' => ['calories' => 155, 'protein' => 13, 'carbs' => 1.1, 'fat' => 11],
            'eggs' => ['calories' => 155, 'protein' => 13, 'carbs' => 1.1, 'fat' => 11],
            'bread' => ['calories' => 265, 'protein' => 9, 'carbs' => 49, 'fat' => 3.2],
            'rice' => ['calories' => 130, 'protein' => 2.7, 'carbs' => 28, 'fat' => 0.3],
            'chicken' => ['calories' => 239, 'protein' => 27, 'carbs' => 0, 'fat' => 14],
            'milk' => ['calories' => 42, 'protein' => 3.4, 'carbs' => 5, 'fat' => 1],
            'cheese' => ['calories' => 402, 'protein' => 25, 'carbs' => 1.3, 'fat' => 33],
            'pasta' => ['calories' => 131, 'protein' => 5, 'carbs' => 25, 'fat' => 1],
            'beef' => ['calories' => 250, 'protein' => 26, 'carbs' => 0, 'fat' => 17],
            'fish' => ['calories' => 206, 'protein' => 22, 'carbs' => 0, 'fat' => 13],
            'potato' => ['calories' => 77, 'protein' => 2, 'carbs' => 17, 'fat' => 0.1],
            'tomato' => ['calories' => 18, 'protein' => 0.9, 'carbs' => 3.9, 'fat' => 0.2],
            'salad' => ['calories' => 15, 'protein' => 1.3, 'carbs' => 2.9, 'fat' => 0.2],
            'yogurt' => ['calories' => 59, 'protein' => 10, 'carbs' => 3.6, 'fat' => 0.4],
        ];

        $queryLower = strtolower($query);
        $matchedFood = null;

        foreach ($foodDatabase as $food => $nutrition) {
            if (str_contains($queryLower, $food)) {
                $matchedFood = $food;
                break;
            }
        }

        if ($matchedFood) {
            $nutrition = $foodDatabase[$matchedFood];
            return [
                'calories' => round($nutrition['calories'] * $quantity, 2),
                'protein' => round($nutrition['protein'] * $quantity, 2),
                'carbs' => round($nutrition['carbs'] * $quantity, 2),
                'fat' => round($nutrition['fat'] * $quantity, 2),
                'fiber' => 0,
                'sugar' => 0,
                'sodium' => 0,
                'serving_size_g' => 100 * $quantity,
                'items' => [],
                'is_fallback' => true,
                'matched_food' => $matchedFood,
                'quantity_multiplier' => $quantity,
                'source' => 'fallback_database',
            ];
        }

        // Default fallback
        return [
            'calories' => round(200 * $quantity, 2),
            'protein' => round(15 * $quantity, 2),
            'carbs' => round(20 * $quantity, 2),
            'fat' => round(10 * $quantity, 2),
            'fiber' => 0,
            'sugar' => 0,
            'sodium' => 0,
            'serving_size_g' => 100 * $quantity,
            'items' => [],
            'is_fallback' => true,
            'matched_food' => 'unknown',
            'quantity_multiplier' => $quantity,
            'source' => 'default_fallback',
        ];
    }

    /**
     * Test API connection directly
     */
    public function testConnection(): array
    {
        try {
            $testQuery = '100g banana';
            $url = $this->baseUrl . 'nutrition?query=' . urlencode($testQuery);

            Log::info('Testing API connection', [
                'url' => $url,
                'api_key_exists' => !empty($this->apiKey),
                'api_key_preview' => !empty($this->apiKey) ? substr($this->apiKey, 0, 8) . '...' : 'missing'
            ]);

            $response = $this->client->get('nutrition', [
                'query' => ['query' => $testQuery],
                'timeout' => 5,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $body = json_decode($responseBody, true);

            return [
                'status' => 'connected',
                'status_code' => $statusCode,
                'test_query' => $testQuery,
                'response_valid_json' => json_last_error() === JSON_ERROR_NONE,
                'has_items' => isset($body['items']) && is_array($body['items']),
                'items_count' => isset($body['items']) ? count($body['items']) : 0,
                'response_sample' => isset($body['items'][0]) ? $body['items'][0] : null,
                'message' => 'API is responding normally'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'disconnected',
                'error' => $e->getMessage(),
                'api_key_set' => !empty($this->apiKey),
                'message' => 'API connection failed'
            ];
        }
    }

    /**
     * Generate cache key
     */
    private function generateCacheKey(string $query): string
    {
        $normalized = strtolower(trim($query));
        return 'calorieninjas:' . md5($normalized);
    }
}