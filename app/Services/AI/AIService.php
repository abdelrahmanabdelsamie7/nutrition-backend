<?php

namespace App\Services;

use App\Services\External\GeminiAIService;
use Illuminate\Support\Facades\{Cache};
use App\Models\User;

class AIService
{
    private GeminiAIService $geminiService;

    public function __construct(GeminiAIService $geminiService)
    {
        $this->geminiService = $geminiService;
    }


    public function generateNutritionRecommendations(
        User $user,
        array $nutritionSummary,
        array $trainingSummary,
        string $language = 'arabic'
    ): array {
        $cacheKey = $this->generateCacheKey('recommendations', $user->id, $language);

        try {
            $userData = $this->prepareUserData($user);

            $maxRetries = 3;
            $lastError = null;

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    $recommendations = $this->geminiService->generateRecommendation(
                        $userData,
                        $nutritionSummary,
                        $trainingSummary,
                        $language
                    );
                    if (($recommendations['ai_model'] ?? '') === 'gemini' &&
                        ($recommendations['is_fallback'] ?? true) === false
                    ) {
                        Cache::put($cacheKey, $recommendations, now()->addHours(6));

                        return $recommendations;
                    }

                } catch (\Exception $e) {
                    $lastError = $e;


                    if ($attempt < $maxRetries) {
                        sleep(1); 
                    }
                }
            }

            return $this->getFallbackRecommendations($user, $language);
        } catch (\Exception $e) {
 
            return $this->getFallbackRecommendations($user, $language);
        }
    }

    public function generateMealSuggestions(
        User $user,
        array $budgetConstraints = [],
        string $language = 'arabic'
    ): array {
        $cacheKey = $this->generateCacheKey('meal_suggestions', $user->id, $language);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $userData = $this->prepareUserData($user);
            $suggestions = $this->geminiService->generateMealSuggestions(
                $userData,
                $budgetConstraints,
                $language
            );
            
            $suggestions['user_id'] = $user->id;
            $suggestions['generated_at'] = now()->toISOString();

            Cache::put($cacheKey, $suggestions, now()->addHours(12));

            return $suggestions;
        } catch (\Exception $e) {
            return $this->getFallbackMealSuggestions($user, $language);
        }
    }

    private function prepareUserData(User $user): array
    {
        return [
            'id' => $user->id,
            'age' => $user->age,
            'gender' => $user->gender,
            'height' => $user->height,
            'weight' => $user->weight,
            'goal' => $user->goal,
            'activity_level' => $user->activity_level,
            'diet_type' => $user->diet_type,
            'budget_level' => $user->budget_level ?? 'medium',
            'daily_calorie_target' => $user->daily_calorie_target,
            'daily_protein_target' => $user->daily_protein_target,
            'daily_carbs_target' => $user->daily_carbs_target,
            'daily_fat_target' => $user->daily_fat_target,
            'language' => $user->language ?? 'arabic',
        ];
    }
    
    private function calculateAdherenceScore(array $recommendations, array $nutritionSummary, array $userData): float
    {
        $score = 70.0; 
        
        if (!empty($nutritionSummary['consistency_score'])) {
            $score += $nutritionSummary['consistency_score'] * 0.3;
        }

        return min(max($score, 0), 100);
    }

    private function generateCacheKey(string $type, string $userId, string $language = 'arabic'): string
    {
        return "ai_{$type}_{$userId}_{$language}_" . date('Y-m-d');
    }

    private function getFallbackRecommendations(User $user, string $language = 'arabic'): array
    {
        $goal = $user->goal ?? 'maintain';

        if ($language === 'arabic') {
            $recommendations = [
                'lose_weight' => [
                    'summary' => 'توصيات لفقدان الوزن',
                    'recommendations' => [
                        'nutrition' => ['قلل الحصص الغذائية', 'زد تناول الخضروات'],
                        'training' => ['اجمع بين الكارديو والقوة'],
                        'goals' => ['سجل الوجبات لمدة 7 أيام']
                    ],
                    'encouragement' => 'الاستمرارية هي المفتاح!'
                ],
                'build_muscle' => [
                    'summary' => 'توصيات لبناء العضلات',
                    'recommendations' => [
                        'nutrition' => ['زد تناول البروتين', 'تناول وجبات متكررة'],
                        'training' => ['ركز على الأحمال الثقيلة'],
                        'goals' => ['زد وزن التمرين أسبوعياً']
                    ],
                    'encouragement' => 'التقدم التدريجي هو الأساس!'
                ],
                'maintain' => [
                    'summary' => 'توصيات للحفاظ على الوزن',
                    'recommendations' => [
                        'nutrition' => ['حافظ على التوازن الغذائي'],
                        'training' => ['تنوع في التمارين'],
                        'goals' => ['حافظ على النشاط اليومي']
                    ],
                    'encouragement' => 'الحفاظ على النتائج يتطلب استمرارية!'
                ]
            ];
        } else {
            $recommendations = [
                'lose_weight' => [
                    'summary' => 'Weight loss recommendations',
                    'recommendations' => [
                        'nutrition' => ['Reduce portion sizes', 'Increase vegetable intake'],
                        'training' => ['Combine cardio and strength'],
                        'goals' => ['Log meals for 7 days']
                    ],
                    'encouragement' => 'Consistency is key!'
                ],
                'build_muscle' => [
                    'summary' => 'Muscle building recommendations',
                    'recommendations' => [
                        'nutrition' => ['Increase protein intake', 'Eat frequent meals'],
                        'training' => ['Focus on heavy weights'],
                        'goals' => ['Increase workout weight weekly']
                    ],
                    'encouragement' => 'Progressive overload is essential!'
                ],
                'maintain' => [
                    'summary' => 'Weight maintenance recommendations',
                    'recommendations' => [
                        'nutrition' => ['Maintain balanced diet'],
                        'training' => ['Vary your exercises'],
                        'goals' => ['Maintain daily activity']
                    ],
                    'encouragement' => 'Maintaining results requires consistency!'
                ]
            ];
        }

        $fallback = $recommendations[$goal] ?? $recommendations['maintain'];
        $fallback['is_fallback'] = true;
        $fallback['generated_at'] = now()->toISOString();
        $fallback['model_used'] = 'fallback';
        $fallback['user_id'] = $user->id;
        $fallback['adherence_score'] = 75.0;

        return $fallback;
    }

    private function getFallbackMealSuggestions(User $user, string $language = 'arabic'): array
    {
        $budget = $user->budget_level ?? 'medium';

        if ($language === 'arabic') {
            return [
                'meal_plans' => [
                    [
                        'name' => 'خطة غذائية متوازنة',
                        'breakfast' => ['description' => 'شوفان مع موز', 'calories' => 350, 'protein' => 15],
                        'lunch' => ['description' => 'دجاج مشوي مع أرز', 'calories' => 500, 'protein' => 35],
                        'dinner' => ['description' => 'سمك مع خضروات', 'calories' => 400, 'protein' => 30],
                        'total_calories' => 1250,
                        'total_protein' => 80,
                        'estimated_cost' => '15-25 دولار'
                    ]
                ],
                'is_fallback' => true,
                'language' => 'arabic'
            ];
        } else {
            return [
                'meal_plans' => [
                    [
                        'name' => 'Balanced Meal Plan',
                        'breakfast' => ['description' => 'Oatmeal with banana', 'calories' => 350, 'protein' => 15],
                        'lunch' => ['description' => 'Grilled chicken with rice', 'calories' => 500, 'protein' => 35],
                        'dinner' => ['description' => 'Fish with vegetables', 'calories' => 400, 'protein' => 30],
                        'total_calories' => 1250,
                        'total_protein' => 80,
                        'estimated_cost' => '$15-25'
                    ]
                ],
                'is_fallback' => true,
                'language' => 'english'
            ];
        }
    }
}