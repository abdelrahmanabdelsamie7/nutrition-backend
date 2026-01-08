<?php

namespace App\Services\Nutrition;

use App\DTOs\NutritionDataDTO;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use App\Services\Voice\VoiceService;
use App\Services\External\CalorieNinjasService;
use App\Interfaces\Services\NutritionServiceInterface;
use App\Services\Translation\ArabicTranslationService;
use App\Interfaces\Repositories\FoodLogRepositoryInterface;

class NutritionService implements NutritionServiceInterface
{
    public function __construct(
        private FoodLogRepositoryInterface $foodLogRepository,
        private CalorieNinjasService $calorieService,
        private ArabicTranslationService $translationService,
        private VoiceService $voiceService
    ) {}

    public function processTextInput(string $text, string $userId): NutritionDataDTO
    {
        $originalText = $text;
        $isArabic = $this->translationService->containsArabic($text);

        if ($isArabic) {
            $translatedText = $this->translateArabicFoodText($text);
            $text = $translatedText;
        }

        try {
            $nutritionData = $this->calorieService->getNutritionData($text);
            
            $parsedItems = [];
            foreach ($nutritionData['items'] as $item) {
                $parsedItems[] = [
                    'original_text' => $originalText,
                    'food' => $item['name'],
                    'quantity' => 1,
                    'unit' => 'grams',
                    'parsed_correctly' => true,
                    'nutrition' => [
                        'calories' => $item['calories'] ?? 0,
                        'protein' => $item['protein_g'] ?? 0,
                        'carbs' => $item['carbs_g'] ?? 0,
                        'fat' => $item['fat_g'] ?? 0,
                    ],
                    'details' => [
                        'is_fallback' => false,
                        'source' => 'calorieninjas',
                        'matched_food' => $item['name'],
                        'serving_size_g' => $item['serving_size_g'] ?? 100,
                    ]
                ];
            }

            $dto = new NutritionDataDTO(
                userId: $userId,
                rawInput: $originalText,
                parsedItems: $parsedItems,
                calories: $nutritionData['calories'],
                protein: $nutritionData['protein'],
                carbs: $nutritionData['carbs'],
                fat: $nutritionData['fat'],
                source: 'manual',
                apiResponse: $nutritionData,
                apiSource: 'calorieninjas',
                isCached: false,
                loggedAt: now()->toDateTimeString()
            );

            $this->foodLogRepository->create($dto->toArray());

            return $dto;
        } catch (\Exception $e) {
            throw new \Exception("فشل في معالجة البيانات الغذائية: " . $e->getMessage());
        }
    }

    public function processVoiceInput(UploadedFile $audioFile, string $userId): NutritionDataDTO
    {
        Log::info('Processing voice input', ['user_id' => $userId]);

        try {
 
            $transcript = $this->voiceService->transcribeAudio($audioFile);
            $cleanTranscript = $this->voiceService->cleanTranscript($transcript);

            Log::info('Voice transcription', [
                'user_id' => $userId,
                'transcript' => $cleanTranscript
            ]);

            $dto = $this->processTextInput($cleanTranscript, $userId);
            $dto->source = 'voice';

            return $dto;
        } catch (\Exception $e) {
            Log::error('Voice input processing failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function parseFoodItems(string $text): array
    {
        return $this->calorieService->parseMultipleItems($text);
    }

    public function getDailySummary(string $userId, string $date): array
    {
        $summary = $this->foodLogRepository->getDailySummary(
            $userId,
            \Carbon\Carbon::parse($date)
        );

        $userRepository = app(\App\Interfaces\Repositories\UserRepositoryInterface::class);
        $user = $userRepository->find($userId);

        $targets = [
            'calories' => $user->daily_calorie_target ?? 2000,
            'protein' => $user->daily_protein_target ?? 150,
            'carbs' => $user->daily_carbs_target ?? 250,
            'fat' => $user->daily_fat_target ?? 67,
        ];

        $percentages = [];
        foreach ($targets as $key => $target) {
            $consumed = $summary['total_' . $key] ?? 0;
            $percentages[$key] = $target > 0 ? ($consumed / $target) * 100 : 0;
        }

        return [
            'date' => $date,
            'summary' => $summary,
            'targets' => $targets,
            'percentages' => $percentages,
            'remaining' => [
                'calories' => max(0, $targets['calories'] - ($summary['total_calories'] ?? 0)),
                'protein' => max(0, $targets['protein'] - ($summary['total_protein'] ?? 0)),
                'carbs' => max(0, $targets['carbs'] - ($summary['total_carbs'] ?? 0)),
                'fat' => max(0, $targets['fat'] - ($summary['total_fat'] ?? 0)),
            ],
            'is_on_track' => $this->isOnTrack($summary, $targets),
        ];
    }

    public function getWeeklySummary(string $userId, string $startDate, string $endDate): array
    {
        $summary = $this->foodLogRepository->getWeeklySummary(
            $userId,
            \Carbon\Carbon::parse($startDate),
            \Carbon\Carbon::parse($endDate)
        );

        $userRepository = app(\App\Interfaces\Repositories\UserRepositoryInterface::class);
        $user = $userRepository->find($userId);

        $dailyTargets = [
            'calories' => $user->daily_calorie_target ?? 2000,
            'protein' => $user->daily_protein_target ?? 150,
            'carbs' => $user->daily_carbs_target ?? 250,
            'fat' => $user->daily_fat_target ?? 67,
        ];

        $weeklyTargets = [];
        foreach ($dailyTargets as $key => $value) {
            $weeklyTargets[$key] = $value * 7;
        }

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'summary' => $summary,
            'targets' => [
                'daily' => $dailyTargets,
                'weekly' => $weeklyTargets,
            ],
            'adherence' => $this->calculateWeeklyAdherence($summary, $weeklyTargets),
            'trends' => $this->analyzeWeeklyTrends($summary),
        ];
    }

    private function translateArabicFoodText(string $text): string
    {
        $quantityData = $this->translationService->extractQuantity($text);
        $quantity = $quantityData['quantity'] ?? 1;

        $textWithoutNumbers = preg_replace('/\d+/u', '', $text);
        $textWithoutNumbers = preg_replace('/\s+/u', ' ', $textWithoutNumbers);
        $textWithoutNumbers = trim($textWithoutNumbers);

        $foodTranslations = [
            'دجاج' => 'chicken',
            'أرز' => 'rice',
            'تفاح' => 'apple',
            'موز' => 'banana',
            'خبز' => 'bread',
            'بيض' => 'egg',
            'لحم' => 'beef',
            'سمك' => 'fish',
            'بطاطس' => 'potato',
            'طماطم' => 'tomato',
            'خيار' => 'cucumber',
            'جزر' => 'carrot',
            'خس' => 'lettuce',
            'حليب' => 'milk',
            'جبن' => 'cheese',
            'زبادي' => 'yogurt',
            'قهوة' => 'coffee',
            'شاي' => 'tea',
            'ماء' => 'water',
        ];

        $translatedFood = $textWithoutNumbers;
        foreach ($foodTranslations as $arabic => $english) {
            if (str_contains($textWithoutNumbers, $arabic)) {
                $translatedFood = $english;
                break;
            }
        }

        if ($translatedFood === $textWithoutNumbers) {
            $translatedFood = $this->translationService->translateFoodText($textWithoutNumbers);
        }

        return $quantity . ' ' . $translatedFood;
    }

    private function isOnTrack(array $summary, array $targets): bool
    {
        $calories = $summary['total_calories'] ?? 0;
        $targetCalories = $targets['calories'] ?? 2000;

        $lowerBound = $targetCalories * 0.85;
        $upperBound = $targetCalories * 1.15;

        return $calories >= $lowerBound && $calories <= $upperBound;
    }

    private function calculateWeeklyAdherence(array $summary, array $targets): float
    {
        $totalCalories = $summary['totals']['total_calories'] ?? 0;
        $targetCalories = $targets['calories'] ?? 14000;

        if ($targetCalories <= 0) {
            return 0;
        }

        $adherence = ($totalCalories / $targetCalories) * 100;
        return min($adherence, 100);
    }

    private function analyzeWeeklyTrends(array $summary): array
    {
        $dailyData = $summary['daily_data'] ?? [];

        if (count($dailyData) < 2) {
            return ['trend' => 'insufficient_data', 'volatility' => 0];
        }

        $calories = array_column($dailyData, 'daily_calories');

        $n = count($calories);
        $sumX = $sumY = $sumXY = $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumX += $i;
            $sumY += $calories[$i];
            $sumXY += $i * $calories[$i];
            $sumX2 += $i * $i;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);

        $mean = array_sum($calories) / $n;
        $variance = 0;
        foreach ($calories as $value) {
            $variance += pow($value - $mean, 2);
        }
        $variance /= $n;
        $stdDev = sqrt($variance);
        $volatility = ($mean > 0) ? ($stdDev / $mean) * 100 : 0;

        return [
            'trend' => $slope > 10 ? 'increasing' : ($slope < -10 ? 'decreasing' : 'stable'),
            'trend_slope' => $slope,
            'volatility' => $volatility,
            'consistency' => 100 - min($volatility, 100),
        ];
    }
}