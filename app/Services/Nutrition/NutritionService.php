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
        $isArabic = $this->translationService->containsArabic($text);

        if ($isArabic) {
            $textForTranslation = preg_replace('/\d+/u', '', $text); // إزالة الأرقام
            $textForTranslation = preg_replace('/\s+/u', ' ', $textForTranslation); // إزالة مسافات زائدة
            $textForTranslation = trim($textForTranslation);

            if (!empty($textForTranslation)) {
                $translatedFood = $this->translationService->translateFoodText($textForTranslation);

                preg_match('/(\d+(?:\.\d+)?)/', $text, $matches);
                $quantity = $matches[1] ?? 1;

                $text = $quantity . ' ' . $translatedFood;
            }
        }

        $originalText = $text;

        $quantityData = $isArabic ?
            $this->translationService->extractQuantity($text) :
            ['quantity' => 1, 'unit' => ''];

        if ($isArabic) {
            $textForTranslation = preg_replace('/\d+\s*\p{L}*/u', '', $originalText);
            $textForTranslation = trim($textForTranslation);

            if (!empty($textForTranslation)) {
                $translatedFood = $this->translationService->translateFoodText($textForTranslation);
                $text = $quantityData['quantity'] . ' ' . $translatedFood;
            }
        }

        $nutritionData = $this->calorieService->getNutritionData($text);

        if ($quantityData['quantity'] != 1) {
            $multiplier = $quantityData['quantity'];
            $nutritionData['calories'] *= $multiplier;
            $nutritionData['protein'] *= $multiplier;
            $nutritionData['carbs'] *= $multiplier;
            $nutritionData['fat'] *= $multiplier;
            $nutritionData['quantity_multiplier'] = $multiplier;
        }

        $nutritionData['original_language'] = $isArabic ? 'ar' : 'en';
        $nutritionData['original_query'] = $originalText;
        $nutritionData['processed_query'] = $text;

        $dto = new NutritionDataDTO(
            userId: $userId,
            rawInput: $originalText,
            parsedItems: $this->parseFoodItems($text),
            calories: $nutritionData['calories'],
            protein: $nutritionData['protein'],
            carbs: $nutritionData['carbs'],
            fat: $nutritionData['fat'],
            source: 'manual',
            apiResponse: $nutritionData,
            apiSource: 'calorieninjas',
            isCached: $nutritionData['is_cached'] ?? false,
            loggedAt: now()->toDateTimeString()
        );

        $this->foodLogRepository->create($dto->toArray());

        return $dto;
    }

    public function processVoiceInput(UploadedFile $audioFile, string $userId): NutritionDataDTO
    {
        Log::info('Processing voice input', ['user_id' => $userId, 'size' => $audioFile->getSize()]);

        $transcript = $this->voiceService->transcribeAudio($audioFile);

        $cleanTranscript = $this->voiceService->cleanTranscript($transcript);

        $foodItems = $this->voiceService->extractFoodItemsFromVoice($cleanTranscript);

        $dto = $this->processTextInput($cleanTranscript, $userId);

        $dto->source = 'voice';

        Log::info('Voice input processed successfully', [
            'user_id' => $userId,
            'transcript' => $cleanTranscript,
            'calories' => $dto->calories,
        ]);

        return $dto;
    }

    // public function getNutritionFromExternalAPI(string $foodQuery): array
    // {
    //     return $this->calorieService->getNutritionData($foodQuery);
    // }

    public function parseFoodItems(string $text): array
    {
        $items = [];

        $segments = preg_split('/\s*(?:,|and|with|\+)\s*/i', $text);

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if (!empty($segment)) {
                $items[] = $this->calorieService->parseFoodItems($segment);
            }
        }

        return $items;
    }

    // public function calculateTotals(array $foodItems): array
    // {
    //     $totals = [
    //         'calories' => 0,
    //         'protein' => 0,
    //         'carbs' => 0,
    //         'fat' => 0,
    //         'items' => [],
    //     ];

    //     foreach ($foodItems as $item) {
    //         $nutrition = $this->calorieService->getNutritionData($item['item']);

    //         $quantity = $item['quantity'] ?? 1;
    //         $nutrition['calories'] *= $quantity;
    //         $nutrition['protein'] *= $quantity;
    //         $nutrition['carbs'] *= $quantity;
    //         $nutrition['fat'] *= $quantity;

    //         $totals['calories'] += $nutrition['calories'];
    //         $totals['protein'] += $nutrition['protein'];
    //         $totals['carbs'] += $nutrition['carbs'];
    //         $totals['fat'] += $nutrition['fat'];

    //         $totals['items'][] = [
    //             'item' => $item,
    //             'nutrition' => $nutrition,
    //         ];
    //     }

    //     return $totals;
    // }

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
            'consumed' => $summary,
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

        $adherence = $this->calculateWeeklyAdherence($summary, $weeklyTargets);

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'summary' => $summary,
            'targets' => [
                'daily' => $dailyTargets,
                'weekly' => $weeklyTargets,
            ],
            'adherence' => $adherence,
            'trends' => $this->analyzeWeeklyTrends($summary),
        ];
    }

    // public function compareWithTargets(string $userId, array $intake): array
    // {
    //     $userRepository = app(\App\Interfaces\Repositories\UserRepositoryInterface::class);
    //     $user = $userRepository->find($userId);

    //     $targets = [
    //         'calories' => $user->daily_calorie_target ?? 2000,
    //         'protein' => $user->daily_protein_target ?? 150,
    //         'carbs' => $user->daily_carbs_target ?? 250,
    //         'fat' => $user->daily_fat_target ?? 67,
    //     ];

    //     $comparison = [];
    //     foreach ($targets as $key => $target) {
    //         $actual = $intake[$key] ?? 0;
    //         $difference = $actual - $target;
    //         $percentage = $target > 0 ? ($actual / $target) * 100 : 0;

    //         $comparison[$key] = [
    //             'actual' => $actual,
    //             'target' => $target,
    //             'difference' => $difference,
    //             'percentage' => $percentage,
    //             'status' => $this->getStatus($difference, $key),
    //         ];
    //     }

    //     return $comparison;
    // }

    private function isOnTrack(array $summary, array $targets): bool
    {
        $calories = $summary['total_calories'] ?? 0;
        $targetCalories = $targets['calories'] ?? 2000;

        $lowerBound = $targetCalories * 0.9;
        $upperBound = $targetCalories * 1.1;

        return $calories >= $lowerBound && $calories <= $upperBound;
    }

    private function calculateWeeklyAdherence(array $summary, array $targets): float
    {
        $totalCalories = $summary['totals']['total_calories'] ?? 0;
        $targetCalories = $targets['calories'] ?? 14000; // 2000 * 7

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

    private function getStatus(float $difference, string $metric): string
    {
        $tolerances = [
            'calories' => 100,
            'protein' => 20,
            'carbs' => 30,
            'fat' => 10,
        ];

        $tolerance = $tolerances[$metric] ?? 50;

        if (abs($difference) <= $tolerance) {
            return 'on_target';
        } elseif ($difference > 0) {
            return 'over';
        } else {
            return 'under';
        }
    }
}
