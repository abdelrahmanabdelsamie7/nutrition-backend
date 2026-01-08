<?php

namespace App\Services\AI;

use App\Interfaces\Services\AIServiceInterface;
use App\Services\External\GeminiAIService;
use App\Services\External\OpenAIService;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

class AIService implements AIServiceInterface
{
    private GeminiAIService $geminiService;
    private OpenAIService $openAIService;
    private string $primaryModel = 'gemini';

    public function __construct(
        GeminiAIService $geminiService,
        OpenAIService $openAIService
    ) {
        $this->geminiService = $geminiService;
        $this->openAIService = $openAIService;
    }

    /**
     * Generate nutrition recommendations
     */
    public function generateNutritionRecommendations(
        User $user,
        array $nutritionSummary,
        array $trainingSummary
    ): array {
        $cacheKey = $this->generateCacheKey('recommendations', $user->id);

        if (Cache::has($cacheKey)) {
            Log::info('Using cached AI recommendations', ['user_id' => $user->id]);
            return Cache::get($cacheKey);
        }

        $userData = $this->prepareUserData($user);

        try {
            Log::info('Generating AI recommendations', [
                'user_id' => $user->id,
                'model' => $this->primaryModel
            ]);

            $recommendations = $this->geminiService->generateRecommendation(
                $userData,
                $nutritionSummary,
                $trainingSummary
            );

            // Add metadata
            $recommendations['generated_at'] = now()->toISOString();
            $recommendations['model_used'] = $this->primaryModel;
            $recommendations['user_id'] = $user->id;

            // Calculate adherence score
            $recommendations['adherence_score'] = $this->calculateAdherenceScore(
                $recommendations,
                $nutritionSummary
            );

            // Cache for 6 hours
            Cache::put($cacheKey, $recommendations, now()->addHours(6));

            Log::info('AI recommendations generated successfully', [
                'user_id' => $user->id,
                'adherence_score' => $recommendations['adherence_score']
            ]);

            return $recommendations;
        } catch (\Exception $e) {
            Log::error('Primary AI model failed, trying fallback', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            // Try fallback to OpenAI
            return $this->generateWithFallback($userData, $nutritionSummary, $trainingSummary);
        }
    }

    /**
     * Generate Arabic nutrition recommendations
     */
    public function generateArabicNutritionRecommendations(
        User $user,
        array $nutritionSummary,
        array $trainingSummary
    ): array {
        $cacheKey = $this->generateCacheKey('arabic_recommendations', $user->id);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // First get English recommendations
        $englishRecommendations = $this->generateNutritionRecommendations(
            $user,
            $nutritionSummary,
            $trainingSummary
        );

        // Translate to Arabic
        $arabicRecommendations = $this->translateToArabic($englishRecommendations);

        // Cache for 6 hours
        Cache::put($cacheKey, $arabicRecommendations, now()->addHours(6));

        return $arabicRecommendations;
    }

    /**
     * Generate meal suggestions
     */
    public function generateMealSuggestions(
        User $user,
        array $nutritionData,
        array $budgetConstraints
    ): array {
        $cacheKey = $this->generateCacheKey('meal_suggestions', $user->id);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $userData = $this->prepareUserData($user);

        try {
            $suggestions = $this->geminiService->generateMealSuggestions(
                $userData,
                $budgetConstraints
            );

            // Add cost analysis
            $suggestions['cost_analysis'] = $this->analyzeCosts($suggestions, $user->budget_level);

            // Cache for 12 hours
            Cache::put($cacheKey, $suggestions, now()->addHours(12));

            return $suggestions;
        } catch (\Exception $e) {
            Log::error('Failed to generate meal suggestions', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return $this->getFallbackMealSuggestions($user);
        }
    }

    /**
     * Generate Arabic meal suggestions
     */
    public function generateArabicMealSuggestions(
        User $user,
        array $nutritionData,
        array $budgetConstraints
    ): array {
        $cacheKey = $this->generateCacheKey('arabic_meal_suggestions', $user->id);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Get English suggestions
        $englishSuggestions = $this->generateMealSuggestions($user, $nutritionData, $budgetConstraints);

        // Translate to Arabic
        $arabicSuggestions = $this->translateMealSuggestionsToArabic($englishSuggestions);

        Cache::put($cacheKey, $arabicSuggestions, now()->addHours(12));

        return $arabicSuggestions;
    }

    /**
     * Generate training recommendations
     */
    public function generateTrainingRecommendations(User $user, array $trainingHistory): array
    {
        $cacheKey = $this->generateCacheKey('training_recommendations', $user->id);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $userData = $this->prepareUserData($user);

        // Prepare training data
        $trainingData = [
            'history' => $trainingHistory,
            'goals' => $user->goal,
            'activity_level' => $user->activity_level,
            'current_routine' => $this->extractCurrentRoutine($trainingHistory)
        ];

        $prompt = $this->buildTrainingPrompt($userData, $trainingData);

        try {
            $response = $this->getAIResponse($prompt, [
                'temperature' => 0.8,
                'max_tokens' => 800
            ]);

            $recommendations = $this->parseTrainingResponse($response);

            // Add suitability score
            $recommendations['suitability_score'] = $this->calculateSuitabilityScore(
                $recommendations,
                $userData
            );

            Cache::put($cacheKey, $recommendations, now()->addHours(24));

            return $recommendations;
        } catch (\Exception $e) {
            Log::error('Failed to generate training recommendations', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return $this->getFallbackTrainingRecommendations($user);
        }
    }

    /**
     * Generate Arabic training recommendations
     */
    public function generateArabicTrainingRecommendations(User $user, array $trainingHistory): array
    {
        $cacheKey = $this->generateCacheKey('arabic_training_recommendations', $user->id);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Get English recommendations
        $englishRecommendations = $this->generateTrainingRecommendations($user, $trainingHistory);

        // Translate to Arabic
        $arabicRecommendations = $this->translateTrainingToArabic($englishRecommendations);

        Cache::put($cacheKey, $arabicRecommendations, now()->addHours(24));

        return $arabicRecommendations;
    }

 
    /**
     * Get AI model response
     */
    public function getAIResponse(string $prompt, array $parameters = []): string
    {
        try {
            return $this->geminiService->callGeminiAPI($prompt, $parameters);
        } catch (\Exception $e) {
            Log::warning('Gemini failed, trying OpenAI fallback', ['error' => $e->getMessage()]);

            return $this->openAIService->getCompletion($prompt, $parameters);
        }
    }

    /**
     * Parse AI response to structured data
     */
    public function parseAIResponse(string $response): array
    {
        try {
            $data = json_decode($response, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }

            // Try to extract JSON from text
            preg_match('/\{.*\}/s', $response, $matches);
            if (isset($matches[0])) {
                $data = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $data;
                }
            }

            // Fallback to text analysis
            return $this->parseTextResponse($response);
        } catch (\Exception $e) {
            Log::error('Failed to parse AI response', ['error' => $e->getMessage()]);

            return [
                'raw_response' => $response,
                'parse_error' => $e->getMessage(),
                'summary' => 'AI analysis',
                'recommendations' => []
            ];
        }
    }

    /**
     * Validate AI response
     */
    public function validateAIResponse(array $response): bool
    {
        $requiredKeys = ['summary', 'recommendations'];

        foreach ($requiredKeys as $key) {
            if (!isset($response[$key]) || empty($response[$key])) {
                return false;
            }
        }

        // Check for harmful content
        $blacklistedWords = $this->getBlacklistedWords();
        $responseText = json_encode($response);

        foreach ($blacklistedWords as $word) {
            if (stripos($responseText, $word) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get available AI models
     */
    public function getAvailableModels(): array
    {
        $models = [];

        // Check Gemini availability
        try {
            $test = $this->geminiService->testConnection();
            $models['gemini'] = [
                'name' => 'Gemini Flash 2.0',
                'status' => $test['status'] === 'connected' ? 'available' : 'unavailable',
                'capabilities' => ['recommendations', 'analysis', 'suggestions'],
                'details' => $test
            ];
        } catch (\Exception $e) {
            $models['gemini'] = [
                'name' => 'Gemini Flash 2.0',
                'status' => 'unavailable',
                'error' => $e->getMessage()
            ];
        }

        // Check OpenAI availability
        try {
            $test = $this->openAIService->testConnection();
            $models['openai'] = [
                'name' => 'OpenAI GPT',
                'status' => $test['status'] === 'connected' ? 'available' : 'unavailable',
                'capabilities' => ['recommendations', 'analysis'],
                'details' => $test
            ];
        } catch (\Exception $e) {
            $models['openai'] = [
                'name' => 'OpenAI GPT',
                'status' => 'unavailable',
                'error' => $e->getMessage()
            ];
        }

        return $models;
    }

    /**
     * Translate any response to Arabic
     */
    public function translateToArabic(array $data): array
    {
        $translations = [
            // Keys translation
            'summary' => 'ملخص',
            'recommendations' => 'التوصيات',
            'nutrition' => 'التغذية',
            'training' => 'التدريب',
            'goals' => 'الأهداف',
            'meal_suggestions' => 'اقتراحات الوجبات',
            'breakfast' => 'الفطور',
            'lunch' => 'الغداء',
            'dinner' => 'العشاء',
            'snacks' => 'الوجبات الخفيفة',
            'encouragement' => 'رسالة تشجيعية',
            'adherence_score' => 'معدل الالتزام',
            'generated_at' => 'تم الإنشاء في',
            'model_used' => 'النموذج المستخدم',
            'success' => 'نجاح',
            'message' => 'الرسالة',
            'data' => 'البيانات',
            'period' => 'الفترة',
            'start' => 'بداية',
            'end' => 'نهاية',
            'type' => 'النوع',
            'label' => 'التسمية',
            'nutrition_analysis' => 'تحليل التغذية',
            'training_suggestions' => 'اقتراحات التدريب',
            'overall_rating' => 'التقييم العام',
            'improvement_areas' => 'مجالات التحسين',
            'ai_model' => 'نموذج الذكاء الاصطناعي',
            'is_active' => 'نشط',
            'is_helpful' => 'مفيد',
            'user_feedback' => 'ملاحظات المستخدم',
            'viewed_at' => 'تم المشاهدة في',
            'created_at' => 'تم الإنشاء في',
            'updated_at' => 'تم التحديث في',

            // Values translation
            'Based on your goal to maintain' => 'بناءً على هدفك في الحفاظ على الوزن',
            'Based on your goal to lose weight' => 'بناءً على هدفك في خسارة الوزن',
            'Based on your goal to build muscle' => 'بناءً على هدفك في بناء العضلات',
            'Based on your goal to gain weight' => 'بناءً على هدفك في زيادة الوزن',
            'Track calories to maintain current weight' => 'تتبع السعرات الحرارية للحفاظ على الوزن الحالي',
            'Balance macronutrients for optimal health' => 'وازن المغذيات الكبرى لصحة مثالية',
            'Include variety in your diet' => 'أضف تنوعاً إلى نظامك الغذائي',
            'Listen to hunger and fullness cues' => 'استمع لإشارات الجوع والامتلاء',
            'Aim for 300-500 calorie deficit daily' => 'استهدف عجز 300-500 سعرة حرارية يومياً',
            'Consume 1.2-1.6g protein per kg of body weight' => 'تناول 1.2-1.6 جرام بروتين لكل كيلو جرام من وزن الجسم',
            'Increase vegetable intake for volume' => 'زد من تناول الخضروات للحصول على حجم أكبر',
            'Stay hydrated' => 'احرص على الترطيب',
            'Consume 200-300 calorie surplus' => 'استهلك فائض 200-300 سعرة حرارية',
            'Time protein around workouts' => 'توقيت البروتين حول التمرين',
            'Include complex carbs' => 'أضف الكربوهيدرات المعقدة',
            'Stay consistent with 3-5 sessions weekly' => 'كن منتظماً مع 3-5 جلسات أسبوعياً',
            'Mix different types of exercise' => 'امزج بين أنواع مختلفة من التمارين',
            'Include flexibility and mobility work' => 'أضف تمارين المرونة والحركة',
            'Combine strength training with cardio' => 'اجمع بين تدريب القوة والكارديو',
            'Aim for 10,000+ steps daily' => 'استهدف 10,000+ خطوة يومياً',
            'Include HIIT workouts 2x/week' => 'أضف تمارين HIIT مرتين أسبوعياً',
            'Focus on compound movements' => 'ركز على التمارين المركبة',
            'Progressive overload each week' => 'زود الحمل التدريبي أسبوعياً',
            'Ensure adequate recovery' => 'احرص على التعافي الكافي',
            'Balanced breakfast with protein and complex carbs' => 'فطور متوازن مع بروتين وكربوهيدرات معقدة',
            'Lean protein with vegetables and whole grains' => 'بروتين خالي من الدهون مع خضروات وحبوب كاملة',
            'Similar to lunch, adjust portion size' => 'مشابه للغداء، عدل حجم الحصة',
            'Greek yogurt' => 'زبادي يوناني',
            'Fruit with nuts' => 'فاكهة مع مكسرات',
            'Vegetable sticks' => 'عصي الخضروات',
            'Week of' => 'أسبوع',
            'Recommendation generated successfully' => 'تم إنشاء التوصيات بنجاح',
            'gemini' => 'جيميني',
            'openai' => 'أوبن إيه آي',
            'hardcoded' => 'مدخل يدوياً',
            'true' => 'نعم',
            'false' => 'لا',
            'null' => 'غير محدد',
        ];

        $arabicData = [];

        foreach ($data as $key => $value) {
            $arabicKey = $translations[$key] ?? $key;

            if (is_array($value)) {
                if ($key === 'period' && isset($value['label'])) {
                    // Translate period label with months
                    $value['label'] = $this->translateDateLabel($value['label']);
                }
                $arabicData[$arabicKey] = $this->translateToArabic($value);
            } elseif ($value === null) {
                $arabicData[$arabicKey] = $translations['null'] ?? 'غير محدد';
            } elseif (is_bool($value)) {
                $arabicData[$arabicKey] = $value ? ($translations['true'] ?? 'نعم') : ($translations['false'] ?? 'لا');
            } else {
                $arabicData[$arabicKey] = $translations[(string)$value] ?? $value;
            }
        }

        return $arabicData;
    }

    /**
     * Helper Methods
     */
    private function prepareUserData(User $user): array
    {
        return [
            'id' => $user->id,
            'age' => $user->age,
            'gender' => $user->gender,
            'height' => $user->height,
            'weight' => $user->weight,
            'target_weight' => $user->target_weight,
            'activity_level' => $user->activity_level,
            'goal' => $user->goal,
            'diet_type' => $user->diet_type,
            'budget_level' => $user->budget_level,
            'daily_calorie_target' => $user->daily_calorie_target,
            'daily_protein_target' => $user->daily_protein_target,
            'daily_carbs_target' => $user->daily_carbs_target,
            'daily_fat_target' => $user->daily_fat_target,
            'has_complete_profile' => $this->checkCompleteProfile($user),
        ];
    }

    private function generateWithFallback(
        array $userData,
        array $nutritionSummary,
        array $trainingSummary
    ): array {
        try {
            // Build prompt for OpenAI
            $prompt = $this->buildFallbackPrompt($userData, $nutritionSummary, $trainingSummary);

            $response = $this->openAIService->getCompletion($prompt, [
                'temperature' => 0.7,
                'max_tokens' => 1000
            ]);

            $recommendations = $this->parseAIResponse($response);

            // Add fallback flag
            $recommendations['is_fallback'] = true;
            $recommendations['model_used'] = 'openai';
            $recommendations['generated_at'] = now()->toISOString();

            // Calculate adherence score
            $recommendations['adherence_score'] = $this->calculateAdherenceScore(
                $recommendations,
                $nutritionSummary
            );

            Log::info('Fallback recommendations generated', [
                'user_id' => $userData['id'],
                'model' => 'openai'
            ]);

            return $recommendations;
        } catch (\Exception $e) {
            Log::error('All AI models failed, using hardcoded fallback', [
                'user_id' => $userData['id'],
                'error' => $e->getMessage()
            ]);

            return $this->getHardcodedRecommendations($userData);
        }
    }

    private function buildTrainingPrompt(array $userData, array $trainingData): string
    {
        return <<<PROMPT
        Create a personalized training plan for someone with:

        Profile:
        - Goal: {$userData['goal']}
        - Activity Level: {$userData['activity_level']}
        - Age: {$userData['age']}
        - Current Training: {$trainingData['current_routine']}

        Create a weekly plan with:
        1. Recommended workout split
        2. Specific exercises for each session
        3. Sets and reps recommendations
        4. Progressive overload strategy
        5. Recovery recommendations

        Format as JSON with structure:
        {
            "weekly_plan": {
                "monday": {"focus": "...", "exercises": [...]},
                "tuesday": {...},
                ...
            },
            "progression": {...},
            "recovery_tips": [...],
            "expected_outcomes": [...]
        }
        PROMPT;
    }

    private function parseTrainingResponse(string $response): array
    {
        $parsed = $this->parseAIResponse($response);

        // Ensure structure
        if (!isset($parsed['weekly_plan'])) {
            $parsed['weekly_plan'] = $this->getDefaultTrainingPlan();
        }

        return $parsed;
    }

    private function calculateAdherenceScore(array $recommendations, array $nutritionSummary): float
    {
        // Simplified adherence calculation
        $score = 80.0; // Base score

        // Adjust based on nutrition consistency
        if (isset($nutritionSummary['consistency_score'])) {
            $score = $score * ($nutritionSummary['consistency_score'] / 100);
        }

        // Adjust based on recommendation specificity
        $specificity = $this->calculateSpecificity($recommendations);
        $score = $score * ($specificity / 100);

        return round(min($score, 100), 1);
    }

    private function calculateSpecificity(array $recommendations): float
    {
        $specificity = 70; // Base

        if (!empty($recommendations['meal_suggestions'])) {
            $specificity += 10;
        }

        if (!empty($recommendations['training_suggestions'])) {
            $specificity += 10;
        }

        if (count($recommendations['recommendations'] ?? []) >= 3) {
            $specificity += 10;
        }

        return min($specificity, 100);
    }

    private function calculateSuitabilityScore(array $recommendations, array $userData): float
    {
        $score = 75.0;

        // Adjust based on goal alignment
        $goal = $userData['goal'] ?? 'maintain';
        $planFocus = strtolower(json_encode($recommendations));

        $goalKeywords = [
            'lose_weight' => ['deficit', 'cardio', 'burn', 'reduce'],
            'build_muscle' => ['progressive', 'strength', 'hypertrophy', 'surplus'],
            'maintain' => ['balance', 'consistency', 'moderate', 'sustain']
        ];

        if (isset($goalKeywords[$goal])) {
            $matches = 0;
            foreach ($goalKeywords[$goal] as $keyword) {
                if (strpos($planFocus, $keyword) !== false) {
                    $matches++;
                }
            }
            $score += ($matches / count($goalKeywords[$goal])) * 20;
        }

        return min($score, 100);
    }

    private function analyzeCosts(array $suggestions, string $budgetLevel): array
    {
        $costRanges = [
            'low' => ['min' => 10, 'max' => 15],
            'medium' => ['min' => 15, 'max' => 25],
            'high' => ['min' => 25, 'max' => 35]
        ];

        $range = $costRanges[$budgetLevel] ?? $costRanges['medium'];

        return [
            'budget_level' => $budgetLevel,
            'target_range' => $range,
            'suggestions_within_budget' => true,
            'cost_saving_tips' => $this->getCostSavingTips($budgetLevel)
        ];
    }

    private function getCostSavingTips(string $budgetLevel): array
    {
        $tips = [
            'Buy in bulk when possible',
            'Choose seasonal produce',
            'Plan meals ahead to reduce waste',
            'Cook at home instead of eating out'
        ];

        if ($budgetLevel === 'low') {
            array_push(
                $tips,
                'Focus on inexpensive protein sources like beans and eggs',
                'Use frozen vegetables for cost savings',
                'Prepare larger batches for multiple meals'
            );
        }

        return $tips;
    }

    private function getFallbackMealSuggestions(User $user): array
    {
        $budget = $user->budget_level;

        return [
            'meal_plans' => [
                [
                    'name' => "Balanced {$budget} Budget Plan",
                    'description' => 'Nutrient-dense meals within your budget',
                    'is_fallback' => true
                ]
            ],
            'cost_analysis' => [
                'budget_level' => $budget,
                'estimated_daily_cost' => '$15-20'
            ]
        ];
    }

    private function getFallbackTrainingRecommendations(User $user): array
    {
        $plans = [
            'lose_weight' => [
                'focus' => 'Fat loss and cardio',
                'frequency' => '5-6 days/week',
                'split' => 'Alternate cardio and full-body strength'
            ],
            'build_muscle' => [
                'focus' => 'Strength and hypertrophy',
                'frequency' => '4-5 days/week',
                'split' => 'Push/Pull/Legs or Upper/Lower'
            ],
            'maintain' => [
                'focus' => 'Maintenance and consistency',
                'frequency' => '3-4 days/week',
                'split' => 'Full-body workouts'
            ]
        ];

        $plan = $plans[$user->goal] ?? $plans['maintain'];

        return [
            'weekly_plan' => $this->getDefaultTrainingPlan(),
            'focus' => $plan['focus'],
            'frequency' => $plan['frequency'],
            'split' => $plan['split'],
            'is_fallback' => true
        ];
    }

    private function getHardcodedRecommendations(array $userData): array
    {
        $goal = $userData['goal'] ?? 'maintain';

        $recommendations = [
            'lose_weight' => [
                'summary' => 'Focus on creating a sustainable calorie deficit while maintaining protein intake.',
                'recommendations' => [
                    'nutrition' => [
                        'Aim for 300-500 calorie deficit daily',
                        'Consume 1.2-1.6g protein per kg of body weight',
                        'Increase vegetable intake for volume',
                        'Stay hydrated'
                    ],
                    'training' => [
                        'Combine strength training with cardio',
                        'Aim for 10,000+ steps daily',
                        'Include HIIT workouts 2x/week'
                    ],
                    'goals' => [
                        'Log food daily for 7 days',
                        'Complete 4 workouts this week',
                        'Drink 2L water daily'
                    ]
                ]
            ],
            'build_muscle' => [
                'summary' => 'Focus on progressive overload and adequate protein for muscle growth.',
                'recommendations' => [
                    'nutrition' => [
                        'Consume 200-300 calorie surplus',
                        'Aim for 1.6-2.2g protein per kg',
                        'Time protein around workouts',
                        'Include complex carbs'
                    ],
                    'training' => [
                        'Focus on compound movements',
                        'Progressive overload each week',
                        'Ensure adequate recovery'
                    ],
                    'goals' => [
                        'Increase weights by 5% this week',
                        'Hit protein target daily',
                        'Get 7-8 hours sleep nightly'
                    ]
                ]
            ],
            'maintain' => [
                'summary' => 'Focus on maintaining your current fitness level with balanced nutrition.',
                'recommendations' => [
                    'nutrition' => [
                        'Track calories to maintain current weight',
                        'Balance macronutrients for optimal health',
                        'Include variety in your diet',
                        'Listen to hunger and fullness cues'
                    ],
                    'training' => [
                        'Stay consistent with 3-5 sessions weekly',
                        'Mix different types of exercise',
                        'Include flexibility and mobility work'
                    ],
                    'goals' => [
                        'Maintain weight within ±1kg',
                        'Complete 3-4 workouts weekly',
                        'Get 7+ hours sleep nightly'
                    ]
                ]
            ]
        ];

        $plan = $recommendations[$goal] ?? $recommendations['maintain'];

        return array_merge($plan, [
            'summary' => 'Based on your goal to ' . str_replace('_', ' ', $goal),
            'meal_suggestions' => [
                'breakfast' => 'Balanced breakfast with protein and complex carbs',
                'lunch' => 'Lean protein with vegetables and whole grains',
                'dinner' => 'Similar to lunch, adjust portion size',
                'snacks' => ['Greek yogurt', 'Fruit with nuts', 'Vegetable sticks']
            ],
            'encouragement' => 'Consistency is key! Small daily actions lead to big results.',
            'is_hardcoded' => true,
            'generated_at' => now()->toISOString(),
            'adherence_score' => 85.0,
            'model_used' => 'hardcoded'
        ]);
    }

    private function getDefaultTrainingPlan(): array
    {
        return [
            'monday' => ['focus' => 'Upper Body', 'exercises' => ['Push-ups', 'Rows', 'Shoulder Press']],
            'tuesday' => ['focus' => 'Cardio', 'exercises' => ['Running', 'Cycling', 'Jump Rope']],
            'wednesday' => ['focus' => 'Lower Body', 'exercises' => ['Squats', 'Lunges', 'Calf Raises']],
            'thursday' => ['focus' => 'Active Recovery', 'exercises' => ['Walking', 'Stretching', 'Mobility']],
            'friday' => ['focus' => 'Full Body', 'exercises' => ['Deadlifts', 'Pull-ups', 'Planks']],
            'saturday' => ['focus' => 'Cardio', 'exercises' => ['Swimming', 'Hiking', 'Sports']],
            'sunday' => ['focus' => 'Rest', 'exercises' => ['Complete Rest']]
        ];
    }

    private function extractCurrentRoutine(array $trainingHistory): string
    {
        if (empty($trainingHistory)) {
            return 'Beginner - no established routine';
        }

        $activities = [];
        foreach ($trainingHistory as $session) {
            if (isset($session['activity_type'])) {
                $activities[] = $session['activity_type'];
            }
        }

        $activityCounts = array_count_values($activities);
        arsort($activityCounts);

        $mainActivities = array_slice(array_keys($activityCounts), 0, 3);

        return !empty($mainActivities)
            ? 'Focuses on: ' . implode(', ', $mainActivities)
            : 'Varied routine';
    }

    private function parseTextResponse(string $text): array
    {
        // Simple text parsing for fallback
        $lines = explode("\n", $text);
        $recommendations = [];
        $currentSection = '';

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) continue;

            // Detect sections
            if (preg_match('/^(?:#+\s*)?(SUMMARY|RECOMMENDATIONS|GOALS|TIPS)/i', $line, $matches)) {
                $currentSection = strtolower($matches[1]);
                continue;
            }

            // Bullet points
            if (preg_match('/^[-*•]\s*(.+)/', $line, $matches)) {
                if ($currentSection) {
                    $recommendations[$currentSection][] = $matches[1];
                } else {
                    $recommendations['general'][] = $matches[1];
                }
            }
        }

        return [
            'summary' => $lines[0] ?? 'AI recommendations',
            'recommendations' => $recommendations,
            'raw_text' => $text
        ];
    }

    private function getBlacklistedWords(): array
    {
        return [
            'harmful',
            'dangerous',
            'unsafe',
            'extreme',
            'drug',
            'supplement',
            'fasting',
            'starvation',
        ];
    }

    private function buildFallbackPrompt(
        array $userData,
        array $nutritionSummary,
        array $trainingSummary
    ): string {
        return
            <<<PROMPT
        As a nutrition and fitness coach, provide recommendations for:
        User: {$userData['age']} year old {$userData['gender']}, goal: {$userData['goal']}
        Nutrition: {$nutritionSummary['avg_daily_calories']} avg calories
        Training: {$trainingSummary['total_sessions']} sessions last week
        Provide 3 nutrition recommendations, 2 training recommendations, and 3 weekly goals.
        Format as JSON.
        PROMPT;
    }

    private function generateCacheKey(string $type, string $userId): string
    {
        $date = date('Y-m-d');
        return "ai_{$type}_{$userId}_{$date}";
    }

    private function checkCompleteProfile(User $user): bool
    {
        return !empty($user->age) &&
            !empty($user->gender) &&
            !empty($user->weight) &&
            !empty($user->height) &&
            !empty($user->goal);
    }

    /**
     * Arabic-specific helper methods
     */
    private function translateDateLabel(string $label): string
    {
        $monthTranslations = [
            'Jan' => 'يناير',
            'Feb' => 'فبراير',
            'Mar' => 'مارس',
            'Apr' => 'أبريل',
            'May' => 'مايو',
            'Jun' => 'يونيو',
            'Jul' => 'يوليو',
            'Aug' => 'أغسطس',
            'Sep' => 'سبتمبر',
            'Oct' => 'أكتوبر',
            'Nov' => 'نوفمبر',
            'Dec' => 'ديسمبر'
        ];

        foreach ($monthTranslations as $english => $arabic) {
            $label = str_replace($english, $arabic, $label);
        }

        // Replace "Week of" with "أسبوع"
        $label = str_replace('Week of', 'أسبوع', $label);

        return $label;
    }

    private function translateMealSuggestionsToArabic(array $suggestions): array
    {
        $translations = [
            'Balanced breakfast with protein and complex carbs' => 'فطور متوازن مع بروتين وكربوهيدرات معقدة',
            'Lean protein with vegetables and whole grains' => 'بروتين خالي من الدهون مع خضروات وحبوب كاملة',
            'Similar to lunch, adjust portion size' => 'مشابه للغداء، عدل حجم الحصة',
            'Greek yogurt' => 'زبادي يوناني',
            'Fruit with nuts' => 'فاكهة مع مكسرات',
            'Vegetable sticks' => 'عصي الخضروات',
            'Balanced Budget Plan' => 'خطة ميزانية متوازنة',
            'Nutrient-dense meals within your budget' => 'وجبات غنية بالمغذيات ضمن ميزانيتك',
            'low' => 'منخفض',
            'medium' => 'متوسط',
            'high' => 'مرتفع'
        ];

        return $this->deepTranslate($suggestions, $translations);
    }

    private function translateTrainingToArabic(array $training): array
    {
        $translations = [
            'Upper Body' => 'الجزء العلوي من الجسم',
            'Cardio' => 'كارديو',
            'Lower Body' => 'الجزء السفلي من الجسم',
            'Active Recovery' => 'استشفاء نشط',
            'Full Body' => 'الجسم كامل',
            'Rest' => 'راحة',
            'Push-ups' => 'تمرين الضغط',
            'Rows' => 'تمرين السحب',
            'Shoulder Press' => 'ضغط الكتفين',
            'Running' => 'الجري',
            'Cycling' => 'ركوب الدراجات',
            'Jump Rope' => 'القفز بالحبل',
            'Squats' => 'القرفصاء',
            'Lunges' => 'الاندفاع',
            'Calf Raises' => 'رفع ربلة الساق',
            'Walking' => 'المشي',
            'Stretching' => 'تمارين التمدد',
            'Mobility' => 'الحركة',
            'Deadlifts' => 'الرفعة المميتة',
            'Pull-ups' => 'السحب',
            'Planks' => 'تمرين البلانك',
            'Swimming' => 'السباحة',
            'Hiking' => 'المشي لمسافات طويلة',
            'Sports' => 'الرياضة',
            'Complete Rest' => 'راحة تامة'
        ];

        return $this->deepTranslate($training, $translations);
    }

    private function deepTranslate(array $data, array $translations): array
    {
        $translated = [];

        foreach ($data as $key => $value) {
            $translatedKey = $translations[$key] ?? $key;

            if (is_array($value)) {
                $translated[$translatedKey] = $this->deepTranslate($value, $translations);
            } else {
                $translated[$translatedKey] = $translations[$value] ?? $value;
            }
        }

        return $translated;
    }

    /**
     * Generate complete Arabic response with proper structure
     */
    public function generateCompleteArabicResponse(array $englishData): array
    {
        $arabicData = $this->translateToArabic($englishData);

        // Add Arabic-specific metadata
        $arabicData['language'] = 'ar';
        $arabicData['direction'] = 'rtl';
        $arabicData['translated_at'] = now()->toISOString();

        return $arabicData;
    }

    /**
     * Test Arabic translation
     */
    public function testArabicTranslation(): array
    {
        $testData = [
            'success' => true,
            'message' => 'Recommendation generated successfully',
            'data' => [
                'id' => 'test-123',
                'period' => [
                    'label' => 'Week of Jan 5 - Jan 11'
                ],
                'summary' => 'Based on your goal to maintain',
                'recommendations' => [
                    'nutrition' => [
                        'Track calories to maintain current weight',
                        'Balance macronutrients for optimal health'
                    ],
                    'training' => [
                        'Stay consistent with 3-5 sessions weekly',
                        'Include flexibility and mobility work'
                    ]
                ],
                'meal_suggestions' => [
                    'breakfast' => 'Balanced breakfast with protein and complex carbs',
                    'lunch' => 'Lean protein with vegetables and whole grains'
                ]
            ]
        ];

        return [
            'english' => $testData,
            'arabic' => $this->translateToArabic($testData)
        ];
    }
}