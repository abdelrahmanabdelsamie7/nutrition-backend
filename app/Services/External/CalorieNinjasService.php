<?php

namespace App\Services\External;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Exceptions\ExternalServiceException;
use Whoops\Exception\ErrorException;

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
                'User-Agent' => 'NutritionApp/1.0',
            ],
            'verify' => false, // For testing only, remove in production
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
            return $this->getFallbackNutritionData($query);
        }

        $cacheKey = $this->generateCacheKey($cleanQuery);

        // Check cache first
        if (Cache::has($cacheKey)) {
            Log::info('Cache hit for food query', ['query' => $cleanQuery]);
            return Cache::get($cacheKey);
        }

        try {
            Log::info('Calling CalorieNinjas API', ['query' => $cleanQuery]);

            // Make GET request with query parameter
            $response = $this->client->get('nutrition', [
                'query' => [
                    'query' => $cleanQuery
                ],
                'headers' => [
                    'X-Api-Key' => $this->apiKey
                ],
                'http_errors' => false, // Don't throw exceptions on 4xx/5xx
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            Log::info('CalorieNinjas API Response', [
                'status' => $statusCode,
                'query' => $cleanQuery,
                'response_keys' => array_keys($body)
            ]);

            if ($statusCode !== 200) {
                Log::error('CalorieNinjas API error', [
                    'status' => $statusCode,
                    'body' => $body,
                    'query' => $cleanQuery
                ]);

                throw new ErrorException(
                    "CalorieNinjas API error: {$statusCode}",
                    $statusCode,
                    $body
                );
            }

            if (empty($body['items'])) {
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
        // Remove extra spaces
        $query = trim(preg_replace('/\s+/', ' ', $query));

        // Remove special characters that might break the API
        $query = preg_replace('/[^\w\s\d.,]/u', '', $query);

        // Limit to 1500 characters (API limit)
        if (strlen($query) > 1500) {
            $query = substr($query, 0, 1500);
        }

        // If empty after cleaning, use fallback
        if (empty($query)) {
            return 'apple'; // Default fallback
        }

        return $query;
    }

    /**
     * Normalize API response to standard format
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
        ];

        foreach ($items as $item) {
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
                'quantity' => $this->extractQuantityFromItem($item, $originalQuery),
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
     * Extract quantity from item name
     */
    private function extractQuantityFromItem(array $item, string $query): string
    {
        $name = strtolower($item['name'] ?? '');

        // Try to match quantity patterns
        $patterns = [
            '/(\d+)\s*(?:g|gram|grams)/',
            '/(\d+)\s*(?:kg|kilo|kilos)/',
            '/(\d+)\s*(?:ml|milliliter|milliliters)/',
            '/(\d+)\s*(?:l|liter|liters)/',
            '/(\d+)\s*(?:oz|ounce|ounces)/',
            '/(\d+)\s*(?:lb|pound|pounds)/',
            '/(\d+)\s*(?:piece|pieces|pc|pcs)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $name, $matches)) {
                return $matches[1] . ' ' . $matches[2];
            }
        }

        // Default to 100g (CalorieNinjas default)
        return '100g';
    }

    /**
     * Fallback nutrition data when API fails
     */
    private function getFallbackNutritionData(string $query): array
    {
        Log::warning('Using fallback nutrition data', ['query' => $query]);

        // Common foods with average nutrition values (per 100g)
        $fallbackData = [
            'apple' => ['calories' => 52, 'protein' => 0.3, 'carbs' => 14, 'fat' => 0.2],
            'banana' => ['calories' => 89, 'protein' => 1.1, 'carbs' => 23, 'fat' => 0.3],
            'bread' => ['calories' => 265, 'protein' => 9, 'carbs' => 49, 'fat' => 3.2],
            'rice' => ['calories' => 130, 'protein' => 2.7, 'carbs' => 28, 'fat' => 0.3],
            'chicken' => ['calories' => 239, 'protein' => 27, 'carbs' => 0, 'fat' => 14],
            'egg' => ['calories' => 155, 'protein' => 13, 'carbs' => 1.1, 'fat' => 11],
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

        // Check for quantity in query
        $quantity = 1;
        if (preg_match('/(\d+)/', $queryLower, $matches)) {
            $quantity = (int)$matches[1];
        }

        // Try to match food items
        foreach ($fallbackData as $food => $nutrition) {
            if (str_contains($queryLower, $food)) {
                return [
                    'calories' => $nutrition['calories'] * $quantity,
                    'protein' => $nutrition['protein'] * $quantity,
                    'carbs' => $nutrition['carbs'] * $quantity,
                    'fat' => $nutrition['fat'] * $quantity,
                    'fiber' => 0,
                    'sugar' => 0,
                    'sodium' => 0,
                    'serving_size_g' => 100 * $quantity,
                    'items' => [],
                    'is_fallback' => true,
                    'matched_food' => $food,
                    'quantity_multiplier' => $quantity,
                ];
            }
        }

        // Default fallback
        return [
            'calories' => 200 * $quantity,
            'protein' => 15 * $quantity,
            'carbs' => 20 * $quantity,
            'fat' => 10 * $quantity,
            'fiber' => 0,
            'sugar' => 0,
            'sodium' => 0,
            'serving_size_g' => 100 * $quantity,
            'items' => [],
            'is_fallback' => true,
            'matched_food' => 'unknown',
            'quantity_multiplier' => $quantity,
        ];
    }

    /**
     * Test API connection
     */
    public function testConnection(): array
    {
        try {
            $testQuery = '1 apple';

            $response = $this->client->get('nutrition', [
                'query' => ['query' => $testQuery],
                'headers' => ['X-Api-Key' => $this->apiKey],
                'timeout' => 5,
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            return [
                'status' => 'connected',
                'status_code' => $statusCode,
                'test_query' => $testQuery,
                'response_sample' => $body['items'][0] ?? null,
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