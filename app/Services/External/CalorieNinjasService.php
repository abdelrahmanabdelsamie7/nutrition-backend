<?php

namespace App\Services\External;

use Illuminate\Support\Facades\{Http, Cache};

class CalorieNinjasService
{
    private string $apiUrl;

    public function __construct()
    {
        $this->apiUrl = 'https://api.calorieninjas.com/v1/nutrition';
    }

    public function getNutritionData(string $query): array
    {
        $cleanQuery = $this->cleanQuery($query);

        $cacheKey = 'calorie_ninjas:' . md5($cleanQuery);
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $apiData = $this->callDirectAPI($cleanQuery);
            Cache::put($cacheKey, $apiData, now()->addHours(24));
           
            return $apiData;
        } catch (\Exception $e) {
            throw new \Exception("فشل في الحصول على البيانات الغذائية من CalorieNinjas: " . $e->getMessage());
        }
    }

    private function callDirectAPI(string $query): array
    {
        $response = Http::timeout(15)
            ->retry(3, 1000) 
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Origin' => 'https://calorieninjas.com',
                'Referer' => 'https://calorieninjas.com/',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept-Language' => 'ar-EG,ar;q=0.9,en-US;q=0.8,en;q=0.7',
            ])
            ->get($this->apiUrl, ['query' => $query]);

        $statusCode = $response->status();

        if ($response->successful()) {
            $data = $response->json();
            return $this->normalizeResponse($data, $query);
        }

        if ($statusCode === 429) { 
            sleep(5);
            return $this->callDirectAPI($query);
        }
        $error = $response->body();
        throw new \Exception("CalorieNinjas API Error {$statusCode}: " . substr($error, 0, 150));
    }

    private function cleanQuery(string $query): string
    {
        $removeWords = [
            'last night',
            'we',
            'ordered',
            'a',
            'an',
            'the',
            'ليلة أمس',
            'طلبنا',
            'قمت',
            'بطلب',
            'من',
            'و',
            'ثم'
        ];

        $query = str_ireplace($removeWords, '', $query);
        $query = preg_replace('/\s+/', ' ', $query);

        return trim($query);
    }

    private function normalizeResponse(array $data, string $query): array
    {
        $items = $data['items'] ?? [];

        if (empty($items)) {
            throw new \Exception("لم يتم العثور على بيانات غذائية للاستعلام: {$query}");
        }

        $totals = [
            'calories' => 0,
            'protein' => 0,
            'carbs' => 0,
            'fat' => 0,
            'fiber' => 0,
            'sugar' => 0,
            'sodium' => 0,
            'serving_size_g' => 0,
            'items' => []
        ];

        foreach ($items as $item) {
            $totals['calories'] += $item['calories'] ?? 0;
            $totals['protein'] += $item['protein_g'] ?? 0;
            $totals['carbs'] += $item['carbohydrates_total_g'] ?? 0;
            $totals['fat'] += $item['fat_total_g'] ?? 0;
            $totals['fiber'] += $item['fiber_g'] ?? 0;
            $totals['sugar'] += $item['sugar_g'] ?? 0;
            $totals['sodium'] += $item['sodium_mg'] ?? 0;
            $totals['serving_size_g'] += $item['serving_size_g'] ?? 0;

            $totals['items'][] = [
                'name' => $item['name'] ?? 'Unknown',
                'calories' => $item['calories'] ?? 0,
                'protein_g' => $item['protein_g'] ?? 0,
                'carbs_g' => $item['carbohydrates_total_g'] ?? 0,
                'fat_g' => $item['fat_total_g'] ?? 0,
                'serving_size_g' => $item['serving_size_g'] ?? 0,
            ];
        }

        return [
            'calories' => round($totals['calories'], 2),
            'protein' => round($totals['protein'], 2),
            'carbs' => round($totals['carbs'], 2),
            'fat' => round($totals['fat'], 2),
            'fiber' => round($totals['fiber'], 2),
            'sugar' => round($totals['sugar'], 2),
            'sodium' => round($totals['sodium'], 2),
            'serving_size_g' => round($totals['serving_size_g'], 2),
            'items' => $totals['items'],
            'is_fallback' => false,
            'matched_food' => count($items) > 0 ? ($items[0]['name'] ?? 'unknown') : 'unknown',
            'quantity_multiplier' => 1,
            'source' => 'calorieninjas',
            'original_query' => $query,
            'items_count' => count($items),
            'api_provider' => 'CalorieNinjas',
            'note' => 'Direct API call - no fallback'
        ];
    }

    public function parseMultipleItems(string $text): array
    {
        $text = trim($text);
        return $this->getNutritionData($text);
    }

    public function parseSingleItem(string $text): array
    {
        $text = trim($text);
    
        $nutritionData = $this->getNutritionData($text);

        preg_match('/(\d+(?:\.\d+)?)\s*(g|kg|ml|l|oz|lb|cup|cups|tbsp|tsp|piece|pieces|slice|slices|medium|large|small)?/i', $text, $matches);

        $quantity = $matches[1] ?? 1;
        $unit = strtolower($matches[2] ?? '');

        return [
            'original_text' => $text,
            'food' => $nutritionData['matched_food'] ?? 'unknown',
            'quantity' => $quantity,
            'unit' => $unit ?: 'grams',
            'parsed_correctly' => true,
            'nutrition' => [
                'calories' => $nutritionData['calories'] ?? 0,
                'protein' => $nutritionData['protein'] ?? 0,
                'carbs' => $nutritionData['carbs'] ?? 0,
                'fat' => $nutritionData['fat'] ?? 0,
            ],
            'details' => [
                'is_fallback' => false,
                'source' => 'calorieninjas',
                'matched_food' => $nutritionData['matched_food'] ?? 'unknown',
                'serving_size_g' => $nutritionData['serving_size_g'] ?? 100,
                'items_count' => $nutritionData['items_count'] ?? 1,
            ]
        ];
    }

    public function testConnection(): array
    {
        try {
            $testQuery = '100g chicken breast';
            $response = $this->callDirectAPI($testQuery);

            return [
                'status' => 'connected',
                'message' => 'CalorieNinjas API is working properly',
                'api_provider' => 'CalorieNinjas',
                'api_url' => $this->apiUrl,
                'test_results' => [
                    'query' => $testQuery,
                    'calories' => $response['calories'] ?? 0,
                    'protein' => $response['protein'] ?? 0,
                    'items_count' => $response['items_count'] ?? 0,
                    'matched_food' => $response['matched_food'] ?? 'unknown'
                ],
                'caching_enabled' => true,
                'cache_duration' => '24 hours',
                'rate_limit_handling' => 'Automatic retry with 5s delay',
                'fallback_mode' => 'DISABLED - Direct API only'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'CalorieNinjas API connection failed',
                'api_provider' => 'CalorieNinjas',
                'error' => $e->getMessage(),
                'api_url' => $this->apiUrl,
                'suggestion' => 'Check if calorieninjas.com is accessible from your server',
                'fallback_mode' => 'DISABLED - Direct API only'
            ];
        }
    }

    public function getStats(): array
    {
        $cacheKeys = [
            'requests_today' => 'calorie_ninjas_requests_' . date('Y-m-d'),
            'total_requests' => 'calorie_ninjas_total_requests',
            'successful_requests' => 'calorie_ninjas_successful_requests',
        ];

        return [
            'api_provider' => 'CalorieNinjas',
            'mode' => 'Direct API only (no fallback)',
            'requests_today' => Cache::get($cacheKeys['requests_today'], 0),
            'total_requests' => Cache::get($cacheKeys['total_requests'], 0),
            'successful_requests' => Cache::get($cacheKeys['successful_requests'], 0),
            'cache_hits' => Cache::get('calorie_ninjas_cache_hits', 0),
            'cache_misses' => Cache::get('calorie_ninjas_cache_misses', 0),
            'cache_effectiveness' => Cache::get('calorie_ninjas_cache_hits', 0) > 0 ?
                round(Cache::get('calorie_ninjas_cache_hits', 0) /
                    max(1, Cache::get('calorie_ninjas_cache_hits', 0) + Cache::get('calorie_ninjas_cache_misses', 0)) * 100, 2) . '%' : '0%',
            'last_successful_call' => Cache::get('calorie_ninjas_last_success', 'Never'),
            'last_error' => Cache::get('calorie_ninjas_last_error', 'None'),
        ];
    }
}