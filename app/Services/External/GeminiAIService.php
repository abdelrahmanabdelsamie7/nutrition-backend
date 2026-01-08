<?php

namespace App\Services\External;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Exception;

class GeminiAIService
{
    private Client $client;
    private string $apiKey;
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');

        if (empty($this->apiKey)) {
            throw new Exception('Gemini API key not configured. Add GEMINI_API_KEY to .env file.');
        }

        $this->baseUrl = config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta/');
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->model = $this->selectBestFreeModel();
    }

    private function selectBestFreeModel(): string
    {
        $preferredModels = [
            'gemini-flash-latest',           
            'gemini-flash-lite-latest',    
            'gemini-2.5-flash',              
            'gemini-2.0-flash',            
            'gemma-3-1b-it',           
        ];

        foreach ($preferredModels as $model) {
            try {
                $response = $this->client->get("models/{$model}", [
                    'query' => ['key' => $this->apiKey],
                    'timeout' => 3,
                ]);

                if ($response->getStatusCode() === 200) {
                    return $model;
                }
            } catch (Exception $e) {
                continue;
            }
        }
        
        $fallbackModels = [
            'gemini-2.5-flash-lite',
            'gemini-2.0-flash-lite',
            'gemma-3-4b-it',
        ];

        foreach ($fallbackModels as $model) {
            try {
                $response = $this->client->get("models/{$model}", [
                    'query' => ['key' => $this->apiKey],
                    'timeout' => 3,
                ]);

                if ($response->getStatusCode() === 200) {
                    return $model;
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return 'gemini-flash-latest'; 
    }

    public function callGeminiAPI(string $prompt, array $parameters = [], string $language = 'arabic'): string
    {
        if (empty($this->apiKey)) {
            throw new Exception('Gemini API key not configured');
        }

        $fullPrompt = $this->getLanguageInstruction($language) . $prompt;

        $cacheKey = 'gemini_cache:' . md5($fullPrompt . json_encode($parameters) . $language);
        
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = $this->client->post("models/{$this->model}:generateContent", [
                'query' => ['key' => $this->apiKey],
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $fullPrompt]
                            ]
                        ]
                    ],
                    'generationConfig' => array_merge([
                        'temperature' => 0.7,
                        'maxOutputTokens' => 1024,
                        'topP' => 0.95,
                        'topK' => 40,
                    ], $parameters),
                    'safetySettings' => [
                        [
                            'category' => 'HARM_CATEGORY_HATE_SPEECH',
                            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                        ],
                        [
                            'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                        ],
                        [
                            'category' => 'HARM_CATEGORY_HARASSMENT',
                            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                        ],
                        [
                            'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                        ]
                    ]
                ],
                'http_errors' => false,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $data = json_decode($responseBody, true);

            if ($statusCode !== 200) {
                throw new Exception(
                    'Gemini API error: ' . ($data['error']['message'] ?? "Status: {$statusCode}")
                );
            }
            $text = $this->extractTextFromResponse($data);

            if (empty($text)) {
                throw new Exception('Empty response from Gemini API');
            }

            Cache::put($cacheKey, $text, now()->addHours(6));

            return $text;
        } catch (RequestException $e) {
            throw new Exception('Gemini API request failed: ' . $e->getMessage());
        } catch (Exception $e) {
            throw $e;
        }
    }

    private function getLanguageInstruction(string $language): string
    {
        return match ($language) {
            'arabic' => "يرجى الرد باللغة العربية فقط. استخدم مصطلحات صحية ورياضية شائعة في العالم العربي.\n\n",
            'english' => "Please respond in English only.\n\n",
            default => "Please respond in {$language} language.\n\n",
        };
    }

    private function extractTextFromResponse(array $response): string
    {
        try {
            if (isset($response['promptFeedback']['blockReason'])) {
                throw new Exception(
                    'Content blocked by safety filter: ' . $response['promptFeedback']['blockReason']
                );
            }
            
            if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
                return trim($response['candidates'][0]['content']['parts'][0]['text']);
            }

       
            if (isset($response['candidates'][0]['text'])) {
                return trim($response['candidates'][0]['text']);
            }

            return '';
        } catch (Exception $e) {
            return '';
        }
    }

    public function generateRecommendation(array $userData, array $nutritionSummary, array $trainingSummary, string $language = 'arabic'): array
    {
        $prompt = $this->buildRecommendationPrompt($userData, $nutritionSummary, $trainingSummary, $language);

        try {
            $response = $this->callGeminiAPI($prompt, [], $language);
            return $this->parseRecommendationResponse($response, $language);
        } catch (Exception $e) {
            return $this->getFallbackRecommendation($userData, $nutritionSummary, $language);
        }
    }

    public function generateMealSuggestions(array $userData, array $budgetConstraints, string $language = 'arabic'): array
    {
        $prompt = $this->buildMealSuggestionPrompt($userData, $budgetConstraints, $language);

        try {
            $response = $this->callGeminiAPI($prompt, [], $language);
            return $this->parseMealSuggestionsResponse($response, $language);
        } catch (Exception $e) {
            return $this->getFallbackMealSuggestions($userData, $language);
        }
    }


    public function buildRecommendationPrompt(array $userData, array $nutritionSummary, array $trainingSummary, string $language = 'arabic'): string
    {
        $userProfile = $this->formatUserProfile($userData);
        $nutritionAnalysis = $this->formatNutritionSummary($nutritionSummary);
        $trainingAnalysis = $this->formatTrainingSummary($trainingSummary);

        return <<<PROMPT
    IMPORTANT: You MUST respond with VALID JSON only. No additional text before or after.

    You are a professional nutrition and fitness coach. Analyze this user's data and provide personalized recommendations.

    USER DATA:
    {$userProfile}

    NUTRITION SUMMARY (Last 7 days):
    {$nutritionAnalysis}

    TRAINING SUMMARY (Last 7 days):
    {$trainingAnalysis}

    Create a personalized plan in {$language} with this EXACT JSON structure:
    {
        "summary": "brief 1-2 sentence summary of current status",
        "recommendations": {
            "nutrition": ["3-5 specific nutrition recommendations"],
            "training": ["2-3 specific training recommendations"],
            "goals": ["3 specific, measurable goals for next week"]
        },
        "meal_suggestions": {
            "breakfast": "specific breakfast suggestion",
            "lunch": "specific lunch suggestion",
            "dinner": "specific dinner suggestion",
            "snacks": ["2-3 snack suggestions"]
        },
        "encouragement": "motivational message"
    }

    Guidelines:
    - All text must be in {$language}
    - Recommendations must be actionable and specific
    - Consider user's goal: {$userData['goal']}
    - Diet type: {$userData['diet_type']}
    - Budget level: {$userData['budget_level']}

    Respond ONLY with the JSON object. Do not add explanations, code blocks, or markdown.
    PROMPT;
    }

 
    public function parseRecommendationResponse(string $response, string $language = 'arabic'): array
    {
        $jsonPattern = '/\{(?:[^{}]|(?R))*\}/';
        preg_match_all($jsonPattern, $response, $matches);

        foreach ($matches[0] ?? [] as $potentialJson) {
            try {
                $data = json_decode($potentialJson, true, 512, JSON_THROW_ON_ERROR);

                if (is_array($data) && !empty($data)) {
                    return $this->validateAndFormatResponse($data, $language);
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return $this->parseTextResponse($response, $language);
    }


    private function validateAndFormatResponse(array $data, string $language): array
    {
        $defaults = $language === 'arabic' ? [
            'summary' => 'توصيات مبنية على تحليل البيانات',
            'recommendations' => [
                'nutrition' => ['ركز على تناول البروتين الكافي'],
                'training' => ['حافظ على انتظام التمارين'],
                'goals' => ['سجل تقدمك يومياً'],
            ],
            'meal_suggestions' => [
                'breakfast' => 'فطور غني بالبروتين',
                'lunch' => 'غداء متوازن',
                'dinner' => 'عشاء خفيف',
                'snacks' => ['فاكهة طازجة', 'مكسرات'],
            ],
            'encouragement' => 'الاستمرارية هي سر النجاح!',
        ] : [
            'summary' => 'Recommendations based on data analysis',
            'recommendations' => [
                'nutrition' => ['Focus on adequate protein intake'],
                'training' => ['Maintain exercise consistency'],
                'goals' => ['Track your progress daily'],
            ],
            'meal_suggestions' => [
                'breakfast' => 'Protein-rich breakfast',
                'lunch' => 'Balanced lunch',
                'dinner' => 'Light dinner',
                'snacks' => ['Fresh fruit', 'Nuts'],
            ],
            'encouragement' => 'Consistency is the key to success!',
        ];

        $merged = array_merge($defaults, $data);

        if (!isset($merged['recommendations']) || !is_array($merged['recommendations'])) {
            $merged['recommendations'] = $defaults['recommendations'];
        } else {
            foreach (['nutrition', 'training', 'goals'] as $key) {
                if (!isset($merged['recommendations'][$key]) || !is_array($merged['recommendations'][$key])) {
                    $merged['recommendations'][$key] = $defaults['recommendations'][$key];
                }
            }
        }

        $merged['ai_model'] = 'gemini';
        $merged['is_fallback'] = false;
        $merged['generated_at'] = now()->toISOString();
        $merged['response_quality'] = 'json'; 

        return $merged;
    }

   
    private function parseTextResponse(string $text, string $language): array
    { 
        $text = trim($text);

        $lines = array_filter(array_map('trim', explode("\n", $text)), function ($line) {
            return !empty($line) && strlen($line) > 5;
        });

        $sections = [
            'nutrition' => [],
            'training' => [],
            'goals' => [],
        ];

        $currentSection = null;

        foreach ($lines as $line) {
            if (preg_match('/غذاء|تغذية|طعام|اكل|سعرات|بروتين|كارب|دهون/i', $line)) {
                $currentSection = 'nutrition';
            } elseif (preg_match('/تمرين|رياضة|تدريب|نشاط|حركة|كارديو|قوة/i', $line)) {
                $currentSection = 'training';
            } elseif (preg_match('/هدف|خطة|مستقبل|تحسين|تطوير/i', $line)) {
                $currentSection = 'goals';
            }

            if (
                $currentSection &&
                strlen($line) > 10 &&
                !preg_match('/:|ملخص|توصية|قسم|section|summary|recommendation/i', $line)
            ) {
                $cleanLine = preg_replace('/^[-\*•]\s*/', '', $line);
                $cleanLine = preg_replace('/\s+/', ' ', $cleanLine);

                if (!in_array($cleanLine, $sections[$currentSection])) {
                    $sections[$currentSection][] = $cleanLine;
                }
            }
        }

        foreach ($sections as &$items) {
            if (empty($items)) {
                if ($language === 'arabic') {
                    $items = ['ابدأ بتغييرات صغيرة يمكنك الالتزام بها'];
                } else {
                    $items = ['Start with small changes you can commit to'];
                }
            }
        }

        return [
            'summary' => $language === 'arabic'
                ? 'توصيات ذكية مبنية على تحليل بياناتك'
                : 'Smart recommendations based on your data analysis',
            'recommendations' => $sections,
            'meal_suggestions' => [
                'breakfast' => $language === 'arabic' ? 'وجبة فطور متكاملة العناصر' : 'Complete breakfast meal',
                'lunch' => $language === 'arabic' ? 'غداء متوازن يدعم أهدافك' : 'Balanced lunch supporting your goals',
                'dinner' => $language === 'arabic' ? 'عشاء خفيف يساعد على التعافي' : 'Light dinner aiding recovery',
                'snacks' => $language === 'arabic' ? ['مكسرات غير مملحة', 'فاكهة موسمية'] : ['Unsalted nuts', 'Seasonal fruit'],
            ],
            'encouragement' => $language === 'arabic'
                ? 'كل رحلة كبيرة تبدأ بخطوة صغيرة. استمر!'
                : 'Every great journey begins with a small step. Keep going!',
            'ai_model' => 'gemini',
            'is_fallback' => false,
            'generated_at' => now()->toISOString(),
            'response_quality' => 'text_parsed',
            'note' => 'Response was parsed from text format',
        ];
    }

    private function getStructuredFallback(string $language = 'arabic'): array
    {
        if ($language === 'arabic') {
            return [
                'summary' => 'تحليل الذكاء الاصطناعي',
                'recommendations' => [
                    'nutrition' => ['راجع كميات وتوقيت وجباتك', 'أضف المزيد من الخضروات', 'حافظ على شرب الماء'],
                    'training' => ['أضف تنوعاً إلى تمارينك', 'ركز على جودة التمرين'],
                    'goals' => ['استهدف التسجيل اليومي المنتظم', 'جرب نوع جديد من التمارين', 'حافظ على جودة النوم'],
                ],
                'meal_suggestions' => [
                    'breakfast' => 'فطور متوازن مع بروتين وكربوهيدرات',
                    'lunch' => 'غداء صحي مع بروتين خفيف',
                    'dinner' => 'عشاء خفيف ومتوازن',
                    'snacks' => ['زبادي يوناني', 'فاكهة مع مكسرات', 'أعواد خضروات'],
                ],
                'encouragement' => 'الاستمرارية هي المفتاح!',
                'is_fallback' => true,
                'generated_at' => now()->toISOString(),
                'ai_model' => 'fallback',
                'language' => 'arabic',
            ];
        } else {
            return [
                'summary' => 'AI Analysis',
                'recommendations' => [
                    'nutrition' => ['Review your meal portions and timing', 'Add more vegetables to your diet', 'Stay hydrated'],
                    'training' => ['Add variety to your workouts', 'Focus on exercise quality'],
                    'goals' => ['Aim for consistent daily logging', 'Try a new type of exercise', 'Maintain sleep quality'],
                ],
                'meal_suggestions' => [
                    'breakfast' => 'Balanced breakfast with protein and carbs',
                    'lunch' => 'Healthy lunch with lean protein',
                    'dinner' => 'Light and balanced dinner',
                    'snacks' => ['Greek yogurt', 'Fruit with nuts', 'Vegetable sticks'],
                ],
                'encouragement' => 'Consistency is key!',
                'is_fallback' => true,
                'generated_at' => now()->toISOString(),
                'ai_model' => 'fallback',
                'language' => 'english',
            ];
        }
    }

    private function getFallbackRecommendation(array $userData, array $nutritionSummary, string $language = 'arabic'): array
    {
        $goal = $userData['goal'] ?? 'maintain';

        if ($language === 'arabic') {
            $recommendations = [
                'lose_weight' => [
                    'nutrition' => [
                        'استهدف عجز 300-500 سعرة حرارية يومياً',
                        'زد تناول البروتين للحفاظ على الكتلة العضلية',
                        'أضف الكثير من الخضروات للألياف والحجم',
                        'اشرب الماء قبل الوجبات للترطيب',
                    ],
                    'training' => [
                        'اجمع بين تمارين الكارديو وتمارين القوة',
                        'استهدف 150+ دقيقة من النشاط المعتدل أسبوعياً',
                        'أضف 2-3 جلسات قوة أسبوعياً',
                    ],
                    'goals' => [
                        'سجل جميع الوجبات لمدة 7 أيام متتالية',
                        'أضف 30 دقيقة نشاط إضافي أسبوعياً',
                        'اشرب 8 أكواب ماء يومياً',
                    ]
                ],
                'build_muscle' => [
                    'nutrition' => [
                        'تناول 1.6-2.2 جرام بروتين لكل كجم من وزن الجسم',
                        'كل بفائض سعرات بسيط (200-300 سعرة)',
                        'وقت تناول البروتين حول التمرين',
                        'أضف الكربوهيدرات المعقدة للطاقة',
                    ],
                    'training' => [
                        'ركز على زيادة الأحمال تدريجياً',
                        'درّب كل مجموعة عضلية 2-3 مرات أسبوعياً',
                        'احصل على راحة كافية بين الجلسات',
                    ],
                    'goals' => [
                        'زِد وزن التمرين في تمرين رئيسي واحد هذا الأسبوع',
                        'احصل على 7-8 ساعات نوم كل ليلة',
                        'تناول وجبة بروتين بعد التمرين خلال ساعة',
                    ]
                ],
                'maintain' => [
                    'nutrition' => [
                        'تابع السعرات للحفاظ على الوزن الحالي',
                        'وازن العناصر الغذائية للصحة المثلى',
                        'أضف تنوعاً إلى نظامك الغذائي',
                        'استمع لإشارات الجوع والامتلاء',
                    ],
                    'training' => [
                        'حافظ على الانتظام ب 3-5 جلسات أسبوعياً',
                        'اخلط بين أنواع التمارين المختلفة',
                        'أضف تمارين المرونة والحركة',
                    ],
                    'goals' => [
                        'حافظ على تسجيل النشاط اليومي',
                        'جرب نوع جديد من التمارين هذا الأسبوع',
                        'ركز على جودة النوم والتعافي',
                    ]
                ],
            ];

            $goalTranslations = [
                'lose_weight' => 'فقدان الوزن',
                'build_muscle' => 'بناء العضلات',
                'maintain' => 'الحفاظ على الوزن',
            ];

            $plan = $recommendations[$goal] ?? $recommendations['maintain'];

            return [
                'summary' => 'بناءً على هدفك في ' . ($goalTranslations[$goal] ?? $goal),
                'recommendations' => $plan,
                'meal_suggestions' => [
                    'breakfast' => 'فطور متوازن مع بروتين وكربوهيدرات معقدة',
                    'lunch' => 'بروتين قليل الدهن مع خضروات وحبوب كاملة',
                    'dinner' => 'مشابه للغداء، عدل حجم الحصة',
                    'snacks' => ['زبادي يوناني', 'فاكهة مع مكسرات', 'أعواد خضروات'],
                ],
                'encouragement' => 'الانتظام هو المفتاح! الأفعال الصغيرة اليومية تؤدي لنتائج كبيرة.',
                'is_fallback' => true,
                'generated_at' => now()->toISOString(),
                'ai_model' => 'fallback',
                'language' => 'arabic',
            ];
        } else {
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
                    'goals' => [
                        'Log all meals for 7 consecutive days',
                        'Add 30 minutes of extra activity weekly',
                        'Drink 8 glasses of water daily',
                    ]
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
                    'goals' => [
                        'Increase weight in one main exercise this week',
                        'Get 7-8 hours of sleep each night',
                        'Have a post-workout protein meal within 1 hour',
                    ]
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
                    'goals' => [
                        'Maintain daily activity logging',
                        'Try one new type of exercise this week',
                        'Focus on sleep quality and recovery',
                    ]
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
                'language' => 'english',
            ];
        }
    }

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


    private function parseMealSuggestionsResponse(string $response, string $language = 'arabic'): array
    {
        try {
            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                preg_match('/\{.*\}/s', $response, $matches);
                if (isset($matches[0])) {
                    $data = json_decode($matches[0], true);
                }
            }

            if (json_last_error() !== JSON_ERROR_NONE || !$data) {
                throw new Exception('Invalid JSON response for meal suggestions');
            }

            $data['generated_at'] = now()->toISOString();
            $data['ai_model'] = $this->model;
            $data['language'] = $language;

            return $data;
        } catch (Exception $e) {

            return $this->getFallbackMealSuggestions([], $language);
        }
    }

    public function getFallbackMealSuggestions(array $userData, string $language = 'arabic')
    {
        $budget = $userData['budget_level'] ?? 'medium';

        if ($language === 'arabic') {
            $costRanges = [
                'low' => '10-15 دولار',
                'medium' => '15-25 دولار',
                'high' => '25-35 دولار',
            ];

            return [
                'meal_plans' => [
                    [
                        'name' => 'الخطة المتوازنة للميزانية',
                        'breakfast' => [
                            'description' => 'شوفان مع موعة وزبدة فول سوداني',
                            'calories' => 350,
                            'protein' => 15,
                        ],
                        'lunch' => [
                            'description' => 'سلطة دجاج مع خضروات متنوعة',
                            'calories' => 450,
                            'protein' => 35,
                        ],
                        'dinner' => [
                            'description' => 'سمك مشوي مع كينوا وبروكلي مطهو على البخار',
                            'calories' => 500,
                            'protein' => 40,
                        ],
                        'snacks' => [
                            ['description' => 'تفاح مع زبدة لوز', 'calories' => 200, 'protein' => 6],
                            ['description' => 'زبادي يوناني', 'calories' => 150, 'protein' => 15],
                        ],
                        'total_calories' => 1650,
                        'total_protein' => 111,
                        'estimated_cost' => $costRanges[$budget] ?? '15-25 دولار',
                    ],
                ],
                'is_fallback' => true,
                'language' => 'arabic',
                'generated_at' => now()->toISOString(),
            ];
        } else {
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
                'language' => 'english',
                'generated_at' => now()->toISOString(),
            ];
        }
    }

    private function buildMealSuggestionPrompt(array $userData, array $budgetConstraints, string $language = 'arabic'): string
    {
        $calorieTarget = $userData['daily_calorie_target'] ?? 2000;
        $proteinTarget = $userData['daily_protein_target'] ?? 150;
        $budget = $userData['budget_level'] ?? 'medium';

        $jsonInstruction = $language === 'arabic'
            ? "جميع الأوصاف والأسماء يجب أن تكون باللغة العربية."
            : "All descriptions and names must be in {$language}.";

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
                    "estimated_cost": "-X"
                }
            ]
        }

        IMPORTANT: {$jsonInstruction}
        Return ONLY JSON.
        PROMPT;
    }
}