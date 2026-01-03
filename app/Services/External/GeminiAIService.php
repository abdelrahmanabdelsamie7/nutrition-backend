<?php

namespace App\Services\External;

use Google\Cloud\AIPlatform\V1\PredictionServiceClient;
use Google\Cloud\AIPlatform\V1\Content;
use Google\Cloud\AIPlatform\V1\Part;
use Google\Cloud\AIPlatform\V1\PredictResponse;
use Google\Cloud\AIPlatform\V1\SafetySetting;
use Google\Cloud\AIPlatform\V1\HarmCategory;
use Google\Cloud\AIPlatform\V1\SafetyRating;
use Google\ApiCore\ApiException;
use Illuminate\Support\Facades\Log;
use App\Exceptions\ExternalServiceException;

class GeminiAIService
{
    private ?PredictionServiceClient $client;
    private string $projectId;
    private string $location;
    private string $modelId;
    private string $apiKey;
    private array $safetySettings;

    public function __construct()
    {
        $this->projectId = config('services.gemini.project_id');
        $this->location = config('services.gemini.location', 'us-central1');
        $this->modelId = config('services.gemini.model_id', 'gemini-2.0-flash-exp');
        $this->apiKey = config('services.gemini.api_key');

        // Initialize safety settings
        $this->safetySettings = $this->initializeSafetySettings();

        // Initialize client (lazy loading)
        $this->client = null;
    }

    /**
     * Generate AI response for nutrition/fitness recommendations
     */
    public function generateRecommendation(array $userData, array $nutritionSummary, array $trainingSummary): array
    {
        $prompt = $this->buildRecommendationPrompt($userData, $nutritionSummary, $trainingSummary);

        try {
            $response = $this->callGeminiAPI($prompt);

            return $this->parseRecommendationResponse($response);
        } catch (ApiException $e) {
            Log::error('Gemini API Exception', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'user_id' => $userData['id'] ?? 'unknown',
            ]);

            return $this->getFallbackRecommendation($userData, $nutritionSummary);
        } catch (\Exception $e) {
            Log::error('Unexpected error in Gemini service', [
                'error' => $e->getMessage(),
                'user_id' => $userData['id'] ?? 'unknown',
            ]);

            return $this->getFallbackRecommendation($userData, $nutritionSummary);
        }
    }

    /**
     * Generate meal suggestions based on budget and preferences
     */
    public function generateMealSuggestions(array $userData, array $budgetConstraints): array
    {
        $prompt = $this->buildMealSuggestionPrompt($userData, $budgetConstraints);

        try {
            $response = $this->callGeminiAPI($prompt);

            return $this->parseMealSuggestionsResponse($response);
        } catch (\Exception $e) {
            Log::error('Failed to generate meal suggestions', [
                'error' => $e->getMessage(),
                'user_id' => $userData['id'] ?? 'unknown',
            ]);

            return $this->getFallbackMealSuggestions($userData);
        }
    }

    /**
     * Analyze nutrition patterns
     */
    public function analyzeNutritionPatterns(array $nutritionData): array
    {
        $prompt = $this->buildAnalysisPrompt($nutritionData);

        try {
            $response = $this->callGeminiAPI($prompt);

            return $this->parseAnalysisResponse($response);
        } catch (\Exception $e) {
            Log::error('Failed to analyze nutrition patterns', [
                'error' => $e->getMessage(),
            ]);

            return [
                'analysis' => 'Unable to analyze patterns at this time.',
                'trends' => [],
                'recommendations' => [],
            ];
        }
    }

    /**
     * Call Gemini API with prompt
     */
    public function callGeminiAPI(string $prompt, array $parameters = []): string
    {
        if (!$this->client) {
            $this->initializeClient();
        }

        try {
            // Build content
            $content = (new Content())
                ->setRole('user')
                ->setParts([(new Part())->setText($prompt)]);

            // Prepare request
            $endpoint = "projects/{$this->projectId}/locations/{$this->location}/publishers/google/models/{$this->modelId}";

            $request = [
                'endpoint' => $endpoint,
                'instances' => [$content],
                'parameters' => array_merge([
                    'temperature' => 0.7,
                    'maxOutputTokens' => 1024,
                    'topP' => 0.95,
                    'topK' => 40,
                ], $parameters),
            ];

            // Add safety settings
            $request['safety_settings'] = $this->safetySettings;

            Log::info('Calling Gemini API', [
                'model' => $this->modelId,
                'prompt_length' => strlen($prompt),
            ]);

            /** @var PredictResponse $apiResponse */
            $apiResponse = $this->client->predict($request);

            $predictions = $apiResponse->getPredictions();

            if (empty($predictions)) {
                throw new ExternalServiceException('No predictions returned from Gemini API');
            }

            $firstPrediction = $predictions[0];
            $content = $firstPrediction->getContent();
            $parts = $content->getParts();

            if (empty($parts)) {
                throw new ExternalServiceException('No content parts in prediction');
            }

            $responseText = $parts[0]->getText();

            // Check safety ratings
            $safetyRatings = $firstPrediction->getSafetyRatings();
            if ($safetyRatings) {
                foreach ($safetyRatings as $rating) {
                    /** @var SafetyRating $rating */
                    if ($rating->getBlocked()) {
                        throw new ExternalServiceException(
                            'Content blocked by safety filter: ' . $rating->getCategory()
                        );
                    }
                }
            }

            Log::info('Gemini API response received', [
                'response_length' => strlen($responseText),
            ]);

            return $responseText;
        } catch (ApiException $e) {
            Log::error('Gemini API error', [
                'status' => $e->getStatus(),
                'details' => $e->getDetails(),
                'metadata' => $e->getMetadata(),
            ]);

            throw $e;
        }
    }

    /**
     * Build recommendation prompt
     */
    private function buildRecommendationPrompt(array $userData, array $nutritionSummary, array $trainingSummary): string
    {
        $userProfile = $this->formatUserProfile($userData);
        $nutritionAnalysis = $this->formatNutritionSummary($nutritionSummary);
        $trainingAnalysis = $this->formatTrainingSummary($trainingSummary);

        return <<<PROMPT
        You are an expert AI nutrition and fitness coach. Your task is to provide personalized, actionable recommendations.

        USER PROFILE:
        {$userProfile}

        NUTRITION SUMMARY (Last 7 days):
        {$nutritionAnalysis}

        TRAINING SUMMARY (Last 7 days):
        {$trainingAnalysis}

        Please provide a weekly recommendation with the following structure:

        1. OVERALL ASSESSMENT (Brief summary of current status)
        2. NUTRITION RECOMMENDATIONS (3-5 specific, actionable items):
           - Focus on macronutrient adjustments
           - Meal timing suggestions
           - Food choices based on diet type: {$userData['diet_type']}
           - Consider budget level: {$userData['budget_level']}
        3. TRAINING RECOMMENDATIONS (2-3 specific items):
           - Activity suggestions
           - Intensity adjustments
           - Recovery tips
        4. ACTIONABLE GOALS FOR NEXT WEEK (3 specific, measurable goals)
        5. POSITIVE REINFORCEMENT (Encouraging message)

        Format your response as valid JSON with this structure:
        {
            "summary": "overall assessment text",
            "recommendations": {
                "nutrition": ["item1", "item2", "item3"],
                "training": ["item1", "item2"],
                "goals": ["goal1", "goal2", "goal3"]
            },
            "meal_suggestions": {
                "breakfast": "suggestion",
                "lunch": "suggestion",
                "dinner": "suggestion",
                "snacks": ["suggestion1", "suggestion2"]
            },
            "encouragement": "positive message"
        }

        IMPORTANT: Return ONLY the JSON, no additional text.
        PROMPT;
    }

    /**
     * Build meal suggestion prompt
     */
    private function buildMealSuggestionPrompt(array $userData, array $budgetConstraints): string
    {
        $calorieTarget = $userData['daily_calorie_target'] ?? 2000;
        $proteinTarget = $userData['daily_protein_target'] ?? 150;
        $budget = $userData['budget_level'] ?? 'medium';

        return <<<PROMPT
        Suggest 3 complete daily meal plans for someone with:
        - Daily calorie target: {$calorieTarget} kcal
        - Daily protein target: {$proteinTarget}g
        - Diet type: {$userData['diet_type']}
        - Budget level: {$budget}
        - Goal: {$userData['goal']}

        For each meal plan, include:
        1. Breakfast with calories and protein
        2. Lunch with calories and protein
        3. Dinner with calories and protein
        4. Two snacks with calories and protein
        5. Total daily calories and protein
        6. Estimated cost per day (based on budget level)

        Budget guidelines:
        - Low: $10-15 per day, focus on staples (rice, beans, eggs, seasonal vegetables)
        - Medium: $15-25 per day, include some variety and protein sources
        - High: $25-35 per day, include premium ingredients and variety

        Format as JSON with structure:
        {
            "meal_plans": [
                {
                    "name": "Plan 1 Name",
                    "breakfast": {"description": "...", "calories": X, "protein": X},
                    "lunch": {"description": "...", "calories": X, "protein": X},
                    "dinner": {"description": "...", "calories": X, "protein": X},
                    "snacks": [
                        {"description": "...", "calories": X, "protein": X},
                        {"description": "...", "calories": X, "protein": X}
                    ],
                    "total_calories": X,
                    "total_protein": X,
                    "estimated_cost": "$X-X"
                }
            ]
        }

        Return ONLY JSON.
        PROMPT;
    }

    /**
     * Parse recommendation response
     */
    private function parseRecommendationResponse(string $response): array
    {
        try {
            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Try to extract JSON if there's extra text
                preg_match('/\{.*\}/s', $response, $matches);
                if (isset($matches[0])) {
                    $data = json_decode($matches[0], true);
                }
            }

            if (json_last_error() !== JSON_ERROR_NONE || !$data) {
                throw new \Exception('Invalid JSON response from AI');
            }

            // Validate required fields
            $required = ['summary', 'recommendations'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    throw new \Exception("Missing required field: {$field}");
                }
            }

            // Add metadata
            $data['generated_at'] = now()->toISOString();
            $data['ai_model'] = $this->modelId;

            return $data;
        } catch (\Exception $e) {
            Log::warning('Failed to parse AI response as JSON, using raw response', [
                'error' => $e->getMessage(),
                'response' => substr($response, 0, 500),
            ]);

            return [
                'summary' => 'AI Analysis',
                'recommendations' => [
                    'nutrition' => ['Review your meal portions and timing'],
                    'training' => ['Consider adding variety to your workouts'],
                    'goals' => ['Aim for consistent daily logging'],
                ],
                'raw_response' => $response,
                'parse_error' => $e->getMessage(),
                'generated_at' => now()->toISOString(),
                'ai_model' => $this->modelId,
            ];
        }
    }

    /**
     * Initialize Gemini client
     */
    private function initializeClient(): void
    {
        if (!$this->apiKey) {
            throw new \Exception('Gemini API key not configured');
        }

        try {
            $this->client = new PredictionServiceClient([
                'credentials' => $this->apiKey,
                'transport' => 'rest',
            ]);

            Log::info('Gemini client initialized successfully');
        } catch (\Exception $e) {
            Log::error('Failed to initialize Gemini client', [
                'error' => $e->getMessage(),
            ]);

            throw new ExternalServiceException(
                'Failed to initialize AI service: ' . $e->getMessage()
            );
        }
    }

    /**
     * Initialize safety settings
     */
    private function initializeSafetySettings(): array
    {
        return [
            (new SafetySetting())
                ->setCategory(HarmCategory::HARM_CATEGORY_HATE_SPEECH)
                ->setThreshold(SafetySetting\HarmBlockThreshold::BLOCK_MEDIUM_AND_ABOVE),
            (new SafetySetting())
                ->setCategory(HarmCategory::HARM_CATEGORY_DANGEROUS_CONTENT)
                ->setThreshold(SafetySetting\HarmBlockThreshold::BLOCK_MEDIUM_AND_ABOVE),
            (new SafetySetting())
                ->setCategory(HarmCategory::HARM_CATEGORY_HARASSMENT)
                ->setThreshold(SafetySetting\HarmBlockThreshold::BLOCK_MEDIUM_AND_ABOVE),
            (new SafetySetting())
                ->setCategory(HarmCategory::HARM_CATEGORY_SEXUALLY_EXPLICIT)
                ->setThreshold(SafetySetting\HarmBlockThreshold::BLOCK_MEDIUM_AND_ABOVE),
        ];
    }

    /**
     * Helper methods for formatting
     */
    private function formatUserProfile(array $userData): string
    {
        return sprintf(
            "Age: %d | Gender: %s | Height: %scm | Weight: %skg\nGoal: %s | Activity: %s | Diet: %s\nDaily Targets: %d kcal, Protein: %dg, Carbs: %dg, Fat: %dg",
            $userData['age'] ?? 30,
            $userData['gender'] ?? 'unknown',
            $userData['height'] ?? 0,
            $userData['weight'] ?? 0,
            $userData['goal'] ?? 'maintain',
            $userData['activity_level'] ?? 'moderate',
            $userData['diet_type'] ?? 'balanced',
            $userData['daily_calorie_target'] ?? 2000,
            $userData['daily_protein_target'] ?? 150,
            $userData['daily_carbs_target'] ?? 250,
            $userData['daily_fat_target'] ?? 67
        );
    }

    private function formatNutritionSummary(array $summary): string
    {
        return sprintf(
            "Avg Daily Calories: %.0f | Protein: %.1fg | Carbs: %.1fg | Fat: %.1fg\nConsistency: %.0f%% | Days Logged: %d/%d",
            $summary['avg_daily_calories'] ?? 0,
            $summary['avg_daily_protein'] ?? 0,
            $summary['avg_daily_carbs'] ?? 0,
            $summary['avg_daily_fat'] ?? 0,
            $summary['consistency_score'] ?? 0,
            $summary['days_logged'] ?? 0,
            $summary['total_days'] ?? 7
        );
    }

    private function formatTrainingSummary(array $summary): string
    {
        return sprintf(
            "Avg Sessions/Week: %.1f | Total Minutes: %d | Calories Burned: %.0f\nMain Activities: %s",
            $summary['avg_sessions_per_week'] ?? 0,
            $summary['total_minutes'] ?? 0,
            $summary['total_calories_burned'] ?? 0,
            implode(', ', $summary['main_activities'] ?? [])
        );
    }

    /**
     * Fallback recommendations when AI fails
     */
    private function getFallbackRecommendation(array $userData, array $nutritionSummary): array
    {
        $goal = $userData['goal'] ?? 'maintain';
        $dietType = $userData['diet_type'] ?? 'balanced';

        $recommendations = [
            'lose_weight' => [
                'nutrition' => [
                    'Aim for a 300-500 calorie deficit daily',
                    'Increase protein intake to preserve muscle mass',
                    'Include plenty of vegetables for fiber and volume',
                    'Stay hydrated - drink water before meals',
                ],
                'training' => [
                    'Combine cardio and strength training',
                    'Aim for 150+ minutes of moderate activity weekly',
                    'Include 2-3 strength sessions per week',
                ],
            ],
            'build_muscle' => [
                'nutrition' => [
                    'Consume 1.6-2.2g protein per kg of body weight',
                    'Eat at a slight calorie surplus (200-300 calories)',
                    'Time protein intake around workouts',
                    'Include complex carbs for energy',
                ],
                'training' => [
                    'Focus on progressive overload',
                    'Train each muscle group 2-3 times weekly',
                    'Ensure adequate recovery between sessions',
                ],
            ],
            'maintain' => [
                'nutrition' => [
                    'Track calories to maintain current weight',
                    'Balance macronutrients for optimal health',
                    'Include variety in your diet',
                    'Listen to hunger and fullness cues',
                ],
                'training' => [
                    'Stay consistent with 3-5 sessions weekly',
                    'Mix different types of exercise',
                    'Include flexibility and mobility work',
                ],
            ],
        ];

        $plan = $recommendations[$goal] ?? $recommendations['maintain'];

        return [
            'summary' => 'Based on your goal to ' . str_replace('_', ' ', $goal),
            'recommendations' => $plan,
            'meal_suggestions' => [
                'breakfast' => 'Balanced breakfast with protein and complex carbs',
                'lunch' => 'Lean protein with vegetables and whole grains',
                'dinner' => 'Similar to lunch, adjust portion size',
                'snacks' => ['Greek yogurt', 'Fruit with nuts', 'Vegetable sticks'],
            ],
            'encouragement' => 'Consistency is key! Small daily actions lead to big results.',
            'is_fallback' => true,
            'generated_at' => now()->toISOString(),
            'ai_model' => 'fallback',
        ];
    }

    private function getFallbackMealSuggestions(array $userData): array
    {
        $budget = $userData['budget_level'] ?? 'medium';
        $costRanges = [
            'low' => '$10-15',
            'medium' => '$15-25',
            'high' => '$25-35',
        ];

        return [
            'meal_plans' => [
                [
                    'name' => 'Balanced Budget Plan',
                    'breakfast' => [
                        'description' => 'Oatmeal with banana and peanut butter',
                        'calories' => 350,
                        'protein' => 15,
                    ],
                    'lunch' => [
                        'description' => 'Chicken salad with mixed vegetables',
                        'calories' => 450,
                        'protein' => 35,
                    ],
                    'dinner' => [
                        'description' => 'Grilled fish with quinoa and steamed broccoli',
                        'calories' => 500,
                        'protein' => 40,
                    ],
                    'snacks' => [
                        ['description' => 'Apple with almond butter', 'calories' => 200, 'protein' => 6],
                        ['description' => 'Greek yogurt', 'calories' => 150, 'protein' => 15],
                    ],
                    'total_calories' => 1650,
                    'total_protein' => 111,
                    'estimated_cost' => $costRanges[$budget] ?? '$15-25',
                ],
            ],
            'is_fallback' => true,
        ];
    }
}
