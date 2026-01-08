<?php

namespace App\Services\External;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class CalorieNinjasService
{
    private string $apiKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.calorie_ninjas.api_key');
        $this->apiUrl = 'https://api.calorieninjas.com/v1/nutrition';

        if (empty($this->apiKey)) {
            throw new \Exception('CalorieNinjas API key is not configured');
        }
    }

    public function getNutritionData(string $query): array
    {
        $cacheKey = 'calorie_ninjas:' . md5($query);
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        try {
            $response = $this->makeApiRequest($query);

            Cache::put($cacheKey, $response, now()->addHours(24));

            return $response;
        } catch (\Exception $e) {
            return $this->getSmartFallback($query);
        }
    }

    private function makeApiRequest(string $query): array
    {

        $response = Http::timeout(15)
            ->retry(3, 1000)
            ->withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])
            ->get($this->apiUrl, ['query' => $query]);

        if ($response->successful()) {
            $data = $response->json();

            return $this->normalizeResponse($data, $query);
        }

        $statusCode = $response->status();
        $body = $response->body();

        if ($statusCode === 401 || str_contains($body, 'Invalid API Key')) {
            throw new \Exception('Invalid CalorieNinjas API Key. Please check your configuration.');
        }

        if ($statusCode === 429) {
            throw new \Exception('Rate limit exceeded. Please try again later.');
        }

        throw new \Exception("API Error {$statusCode}: {$body}");
    }

    private function normalizeResponse(array $data, string $query): array
    {
        $items = $data['items'] ?? [];
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

        return array_merge($totals, [
            'is_fallback' => false,
            'matched_food' => count($items) > 0 ? ($items[0]['name'] ?? 'unknown') : 'unknown',
            'quantity_multiplier' => 1,
            'source' => 'calorieninjas',
            'original_query' => $query,
            'items_count' => count($items)
        ]);
    }

    private function getSmartFallback(string $query): array
    {
        preg_match('/(\d+(?:\.\d+)?)/', $query, $matches);
        $quantity = $matches[1] ?? 1;

        $foodDatabase = [
            'خيار' => ['en' => 'cucumber', 'calories' => 15, 'protein' => 0.7, 'carbs' => 3.6, 'fat' => 0.1],
            'cucumber' => ['en' => 'cucumber', 'calories' => 15, 'protein' => 0.7, 'carbs' => 3.6, 'fat' => 0.1],

            'تفاح' => ['en' => 'apple', 'calories' => 52, 'protein' => 0.3, 'carbs' => 14, 'fat' => 0.2],
            'apple' => ['en' => 'apple', 'calories' => 52, 'protein' => 0.3, 'carbs' => 14, 'fat' => 0.2],

            'موز' => ['en' => 'banana', 'calories' => 89, 'protein' => 1.1, 'carbs' => 23, 'fat' => 0.3],
            'banana' => ['en' => 'banana', 'calories' => 89, 'protein' => 1.1, 'carbs' => 23, 'fat' => 0.3],

            'برتقال' => ['en' => 'orange', 'calories' => 47, 'protein' => 0.9, 'carbs' => 12, 'fat' => 0.1],
            'orange' => ['en' => 'orange', 'calories' => 47, 'protein' => 0.9, 'carbs' => 12, 'fat' => 0.1],

            'جزر' => ['en' => 'carrot', 'calories' => 41, 'protein' => 0.9, 'carbs' => 10, 'fat' => 0.2],
            'carrot' => ['en' => 'carrot', 'calories' => 41, 'protein' => 0.9, 'carbs' => 10, 'fat' => 0.2],

            'طماطم' => ['en' => 'tomato', 'calories' => 18, 'protein' => 0.9, 'carbs' => 3.9, 'fat' => 0.2],
            'tomato' => ['en' => 'tomato', 'calories' => 18, 'protein' => 0.9, 'carbs' => 3.9, 'fat' => 0.2],

            'دجاج' => ['en' => 'chicken', 'calories' => 239, 'protein' => 27, 'carbs' => 0, 'fat' => 14],
            'chicken' => ['en' => 'chicken', 'calories' => 239, 'protein' => 27, 'carbs' => 0, 'fat' => 14],

            'أرز' => ['en' => 'rice', 'calories' => 130, 'protein' => 2.7, 'carbs' => 28, 'fat' => 0.3],
            'rice' => ['en' => 'rice', 'calories' => 130, 'protein' => 2.7, 'carbs' => 28, 'fat' => 0.3],

            'خبز' => ['en' => 'bread', 'calories' => 265, 'protein' => 9, 'carbs' => 49, 'fat' => 3.2],
            'bread' => ['en' => 'bread', 'calories' => 265, 'protein' => 9, 'carbs' => 49, 'fat' => 3.2],
        ];

        $queryLower = strtolower($query);
        $matchedFood = null;
        $foodData = null;

        foreach ($foodDatabase as $key => $data) {
            if (str_contains($queryLower, strtolower($key))) {
                $matchedFood = $data['en'];
                $foodData = $data;
                break;
            }
        }

        if ($matchedFood && $foodData) {
            $result = [
                'calories' => round($foodData['calories'] * $quantity, 2),
                'protein' => round($foodData['protein'] * $quantity, 2),
                'carbs' => round($foodData['carbs'] * $quantity, 2),
                'fat' => round($foodData['fat'] * $quantity, 2),
                'fiber' => 0,
                'sugar' => 0,
                'sodium' => 0,
                'serving_size_g' => 100 * $quantity,
                'items' => [
                    [
                        'name' => $matchedFood,
                        'calories' => $foodData['calories'] * $quantity,
                        'protein_g' => $foodData['protein'] * $quantity,
                        'carbs_g' => $foodData['carbs'] * $quantity,
                        'fat_g' => $foodData['fat'] * $quantity,
                        'serving_size_g' => 100 * $quantity,
                    ]
                ],
                'is_fallback' => true,
                'matched_food' => $matchedFood,
                'quantity_multiplier' => $quantity,
                'source' => 'smart_fallback',
                'original_query' => $query,
                'items_count' => 1,
                'note' => 'Used local nutrition database'
            ];
            return $result;
        }

        return [
            'calories' => round(200 * $quantity, 2),
            'protein' => round(15 * $quantity, 2),
            'carbs' => round(25 * $quantity, 2),
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
            'original_query' => $query,
            'items_count' => 0,
        ];
    }

    public function parseFoodItem(string $text): array
    {
        $text = trim($text);

        $patterns = [
            '/^(?<quantity>\d+(?:\.\d+)?)\s*(?<unit>\w+)?\s*(?<food>.+)$/i',

            '/^(?<amount>\d+(?:\.\d+)?)\s*(?<unit>g|kg|ml|l|oz|lb|cup|cups|tbsp|tsp|piece|pieces|slice|slices|medium|large|small)\s*(?<food>.+)$/i',

            '/^(?<food>.+)$/i'
        ];

        $defaultUnitMap = [
            'g' => 'grams',
            'kg' => 'kilograms',
            'ml' => 'milliliters',
            'l' => 'liters',
            'oz' => 'ounces',
            'lb' => 'pounds',
            'cup' => 'cups',
            'cups' => 'cups',
            'tbsp' => 'tablespoons',
            'tsp' => 'teaspoons',
            'piece' => 'pieces',
            'pieces' => 'pieces',
            'slice' => 'slices',
            'slices' => 'slices',
            'medium' => 'medium',
            'large' => 'large',
            'small' => 'small'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $matches = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                $quantity = 1;
                $unit = null;

                if (isset($matches['quantity'])) {
                    $quantity = (float) $matches['quantity'];
                    $unit = $matches['unit'] ?? null;
                } elseif (isset($matches['amount'])) {
                    $quantity = (float) $matches['amount'];
                    $unit = $matches['unit'] ?? null;
                }

                if ($unit && isset($defaultUnitMap[strtolower($unit)])) {
                    $unit = $defaultUnitMap[strtolower($unit)];
                }

                $food = trim($matches['food'] ?? $text);

                $nutrition = $this->getNutritionData($food);

                $parsedItem = [
                    'original_text' => $text,
                    'food' => $food,
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'parsed_correctly' => true,
                    'nutrition' => [
                        'calories' => $nutrition['calories'] * $quantity,
                        'protein' => $nutrition['protein'] * $quantity,
                        'carbs' => $nutrition['carbs'] * $quantity,
                        'fat' => $nutrition['fat'] * $quantity,
                    ],
                    'details' => [
                        'is_fallback' => $nutrition['is_fallback'] ?? false,
                        'matched_food' => $nutrition['matched_food'] ?? 'unknown',
                        'source' => $nutrition['source'] ?? 'unknown',
                    ]
                ];

                return $parsedItem;
            }
        }

        return [
            'original_text' => $text,
            'food' => $text,
            'quantity' => 1,
            'unit' => null,
            'parsed_correctly' => false,
            'nutrition' => $this->getNutritionData($text),
            'details' => ['note' => 'Could not parse item format']
        ];
    }

    public function parseFoodItems(string $text): array
    {
        $text = trim($text);

        $separators = ['،', ',', 'و', 'and', 'with', '+', 'then', 'ثم'];
        $separatorPattern = '/\s*(?:' . implode('|', array_map('preg_quote', $separators)) . ')\s*/iu';

        $segments = preg_split($separatorPattern, $text);
        $items = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);

            if (!empty($segment)) {
                try {
                    $parsedItem = $this->parseFoodItem($segment);
                    $items[] = $parsedItem;
                } catch (\Exception $e) {
                    $items[] = [
                        'original_text' => $segment,
                        'food' => $segment,
                        'quantity' => 1,
                        'unit' => null,
                        'parsed_correctly' => false,
                        'nutrition' => $this->getNutritionData($segment),
                        'details' => ['error' => 'Parsing failed']
                    ];
                }
            }
        }

        return $items;
    }

}