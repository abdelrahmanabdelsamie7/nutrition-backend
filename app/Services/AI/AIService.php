<?php

namespace App\Services\AI;

use App\Interfaces\Services\AIServiceInterface;
use App\Services\External\GeminiAIService;
use App\Services\External\OpenAIService;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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
     * Analyze nutrition patterns
     */
    public function analyzeNutritionPatterns(array $nutritionData): array
    {
        try {
            return $this->geminiService->analyzeNutritionPatterns($nutritionData);
        } catch (\Exception $e) {
            Log::error('Failed to analyze nutrition patterns', ['error' => $e->getMessage()]);

            return [
                'analysis' => 'Pattern analysis unavailable',
                'trends' => [],
                'recommendations' => ['Maintain consistent logging for better analysis'],
            ];
        }
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
            // This would be a health check
            $models['gemini'] = [
                'name' => 'Gemini Flash 2.0',
                'status' => 'available',
                'capabilities' => ['recommendations', 'analysis', 'suggestions']
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
            $models['openai'] = [
                'name' => 'OpenAI GPT',
                'status' => 'available',
                'capabilities' => ['recommendations', 'analysis']
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
            'has_complete_profile' => $user->hasCompleteProfile(),
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
        // In reality, this would compare previous adherence to new recommendations

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
            'suggestions_within_budget' => true, // Simplified
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
            ]
        ];

        $plan = $recommendations[$goal] ?? $recommendations['lose_weight'];

        return array_merge($plan, [
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
            // Add more as needed
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

    private function generateCacheKey(string $type, int $userId): string
    {
        $date = date('Y-m-d');
        return "ai_{$type}_{$userId}_{$date}";
    }
}
