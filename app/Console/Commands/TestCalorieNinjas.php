<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\External\CalorieNinjasService;

class TestCalorieNinjas extends Command
{
    protected $signature = 'test:calorieninjas {query?}';
    protected $description = 'Test CalorieNinjas API connection';

    public function handle(CalorieNinjasService $service): int
    {
        $this->info('Testing CalorieNinjas API...');

        // Test 1: Check configuration
        $apiKey = config('services.calorie_ninjas.api_key');
        $this->info('API Key: ' . (!empty($apiKey) ? substr($apiKey, 0, 8) . '...' : 'NOT SET'));

        // Test 2: Test connection
        $this->info('Testing API connection...');
        $connection = $service->testConnection();

        $this->table(
            ['Key', 'Value'],
            [
                ['Status', $connection['status']],
                ['Status Code', $connection['status_code']],
                ['Valid JSON', $connection['response_valid_json'] ? 'Yes' : 'No'],
                ['Has Items', $connection['has_items'] ? 'Yes' : 'No'],
                ['Items Count', $connection['items_count']],
                ['Message', $connection['message']],
            ]
        );

        // Test 3: Test specific query
        $query = $this->argument('query') ?? '100g banana';
        $this->info("Testing query: {$query}");

        try {
            $result = $service->getNutritionData($query);

            $this->table(
                ['Nutrient', 'Value'],
                [
                    ['Calories', $result['calories']],
                    ['Protein (g)', $result['protein']],
                    ['Carbs (g)', $result['carbs']],
                    ['Fat (g)', $result['fat']],
                    ['Source', $result['source']],
                    ['Is Fallback', $result['is_fallback'] ?? false ? 'Yes' : 'No'],
                ]
            );

            if (isset($result['matched_food'])) {
                $this->info("Matched food in fallback: " . $result['matched_food']);
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
    }
}