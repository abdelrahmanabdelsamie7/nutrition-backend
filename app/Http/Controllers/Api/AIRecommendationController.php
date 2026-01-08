<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Resources\AIRecommendationResource;
use App\Services\External\GeminiAIService;
use App\Interfaces\Repositories\{
    UserRepositoryInterface,
    FoodLogRepositoryInterface,
    TrainingLogRepositoryInterface,
    AIRecommendationRepositoryInterface
};
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\{Auth};
use Illuminate\Http\Request;

class AIRecommendationController extends BaseController
{
    private GeminiAIService $geminiService;
    private UserRepositoryInterface $userRepository;
    private FoodLogRepositoryInterface $foodRepository;
    private TrainingLogRepositoryInterface $trainingRepository;
    private AIRecommendationRepositoryInterface $aiRepository;

    public function __construct(
        UserRepositoryInterface $userRepository,
        FoodLogRepositoryInterface $foodRepository,
        TrainingLogRepositoryInterface $trainingRepository,
        AIRecommendationRepositoryInterface $aiRepository
    ) {
        $this->middleware('auth:api');

        $this->userRepository = $userRepository;
        $this->foodRepository = $foodRepository;
        $this->trainingRepository = $trainingRepository;
        $this->aiRepository = $aiRepository;

        $this->geminiService = new GeminiAIService();
    }

    public function getWeekly(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $language = $request->input('language', 'arabic');

            $recommendation = $this->aiRepository->getLatestRecommendation($userId);

            if ($recommendation && $this->isRecommendationValid($recommendation)) {
                $message = $language === 'arabic'
                    ? 'تم استرجاع أحدث التوصيات'
                    : 'Latest recommendation retrieved';

                return $this->successResponse(
                    new AIRecommendationResource($recommendation),
                    $message
                );
            }

            $newRecommendation = $this->generateWeeklyRecommendation($userId, $language);

            $message = $language === 'arabic'
                ? 'تم إنشاء توصية جديدة'
                : 'New recommendation generated';

            return $this->successResponse(
                $newRecommendation,
                $message
            );
        } catch (\Exception $e) {
            $errorMessage = $request->input('language', 'arabic') === 'arabic'
                ? 'فشل الحصول على التوصية: ' . $e->getMessage()
                : 'Failed to get recommendation: ' . $e->getMessage();

            return $this->errorResponse($errorMessage, 500);
        }
    }

    public function generate(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $language = $request->input('language', 'arabic');

            $recommendation = $this->generateWeeklyRecommendation($userId, $language);

            $message = $language === 'arabic'
                ? 'تم إنشاء التوصية بنجاح'
                : 'Recommendation generated successfully';

            return $this->successResponse($recommendation, $message);
        } catch (\Exception $e) {
            $errorMessage = $request->input('language', 'arabic') === 'arabic'
                ? 'فشل إنشاء التوصية: ' . $e->getMessage()
                : 'Failed to generate recommendation: ' . $e->getMessage();

            return $this->errorResponse($errorMessage, 500);
        }
    }

    private function generateWeeklyRecommendation(string $userId, string $language = 'arabic')
    {
        try {
            $user = $this->userRepository->find($userId);
            if (!$user) {
                throw new \Exception('User not found');
            }

            $userData = [
                'id' => $userId,
                'age' => $user->age,
                'gender' => $user->gender,
                'height' => $user->height,
                'weight' => $user->weight,
                'goal' => $user->goal,
                'activity_level' => $user->activity_level,
                'diet_type' => $user->diet_type,
                'budget_level' => $user->budget_level,
                'daily_calorie_target' => $user->daily_calorie_target,
                'daily_protein_target' => $user->daily_protein_target,
                'daily_carbs_target' => $user->daily_carbs_target,
                'daily_fat_target' => $user->daily_fat_target,
            ];

            $startDate = now()->subDays(7)->startOfDay();
            $endDate = now()->endOfDay();

            $nutritionSummary = $this->foodRepository->getWeeklySummary($userId, $startDate, $endDate);
            $trainingSummary = $this->trainingRepository->getWeeklySummary($userId, $startDate, $endDate);

            $formattedTrainingSummary = [
                'total_minutes' => $trainingSummary['totals']['total_duration'] ?? 0,
                'total_calories_burned' => $trainingSummary['totals']['total_calories_burned'] ?? 0,
                'main_activities' => array_column($trainingSummary['activity_breakdown'] ?? [], 'activity_type'),
                'avg_sessions_per_week' => $trainingSummary['totals']['total_sessions'] ?? 0,
            ];

            $aiResponse = $this->geminiService->generateRecommendation(
                $userData,
                $nutritionSummary,
                $formattedTrainingSummary,
                $language
            );

            $periodStart = now()->startOfWeek();
            $periodEnd = now()->endOfWeek();

            $exists = $this->aiRepository->existsForPeriod($userId, $periodStart, $periodEnd);
            if ($exists) {
                $this->aiRepository->deactivateOldRecommendations($userId);
            }

            $recommendation = $this->aiRepository->create([
                'user_id' => $userId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'period_type' => 'weekly',
                'summary' => $aiResponse['summary'] ?? '',
                'recommendations' => json_encode($aiResponse['recommendations'] ?? [], JSON_UNESCAPED_UNICODE),
                'meal_suggestions' => json_encode($aiResponse['meal_suggestions'] ?? [], JSON_UNESCAPED_UNICODE),
                'ai_model' => $aiResponse['ai_model'] ?? 'gemini',
                'adherence_score' => $aiResponse['adherence_score'] ?? 0,
                'is_fallback' => $aiResponse['is_fallback'] ?? false,
                'raw_ai_response' => json_encode($aiResponse, JSON_UNESCAPED_UNICODE),
                'is_active' => true,
            ]);

            return new AIRecommendationResource($recommendation);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function isRecommendationValid($recommendation): bool
    {
        $currentWeekStart = now()->startOfWeek();
        $currentWeekEnd = now()->endOfWeek();

        return $recommendation->created_at >= $currentWeekStart &&
            $recommendation->created_at <= $currentWeekEnd;
    }

    public function markAsViewed($id): JsonResponse
    {
        try {
            $this->aiRepository->markAsViewed($id);

            return $this->successResponse(
                null,
                'Recommendation marked as viewed'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to mark as viewed: ' . $e->getMessage(),
                500
            );
        }
    }
 
    public function provideFeedback($id, Request $request): JsonResponse
    {
        try {
            $isHelpful = filter_var($request->input('is_helpful'), FILTER_VALIDATE_BOOLEAN);
            $feedback = $request->input('feedback');

            $this->aiRepository->updateFeedback($id, $isHelpful, $feedback);

            $message = $request->input('language', 'arabic') === 'arabic'
                ? 'تم تقديم الملاحظات بنجاح'
                : 'Feedback submitted';

            return $this->successResponse(null, $message);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to submit feedback: ' . $e->getMessage(),
                500
            );
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();

            $recommendations = $this->aiRepository->getActiveRecommendations($userId);

            return $this->successResponse(
                AIRecommendationResource::collection($recommendations),
                'Recommendations retrieved'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to get recommendations: ' . $e->getMessage(),
                500
            );
        }
    }
}