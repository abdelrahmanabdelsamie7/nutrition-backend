<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Resources\AIRecommendationResource;
use App\Interfaces\Services\AIServiceInterface;
use App\Interfaces\Repositories\{UserRepositoryInterface, FoodLogRepositoryInterface, TrainingLogRepositoryInterface, AIRecommendationRepositoryInterface};
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AIRecommendationController extends BaseController
{
    public function __construct(
        private AIServiceInterface $aiService,
        private UserRepositoryInterface $userRepository,
        private FoodLogRepositoryInterface $foodRepository,
        private TrainingLogRepositoryInterface $trainingRepository,
        private AIRecommendationRepositoryInterface $aiRepository
    ) {
        $this->middleware('auth:api');
    }

    public function getWeekly(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $recommendation = $this->aiRepository->getLatestRecommendation($userId);
            if ($recommendation && $recommendation->isCurrent()) {
                return $this->successResponse(
                    new AIRecommendationResource($recommendation),
                    'Latest recommendation retrieved'
                );
            }
            $newRecommendation = $this->generateWeeklyRecommendation($userId);
            return $this->successResponse(
                $newRecommendation,
                'New recommendation generated'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get recommendation: ' . $e->getMessage(), 500);
        }
    }

    public function generate(): JsonResponse
    {
        try {
            $userId = Auth::id();

            $recommendation = $this->generateWeeklyRecommendation($userId);

            return $this->successResponse($recommendation, 'Recommendation generated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to generate recommendation: ' . $e->getMessage(), 500);
        }
    }

    public function index(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $recommendations = $this->aiRepository->getActiveRecommendations($userId);
            return $this->successResponse(
                AIRecommendationResource::collection($recommendations),
                'Recommendations retrieved'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get recommendations: ' . $e->getMessage(), 500);
        }
    }

    public function markAsViewed($id): JsonResponse
    {
        try {
            $this->aiRepository->markAsViewed($id);
            return $this->successResponse(null, 'Recommendation marked as viewed');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to mark as viewed: ' . $e->getMessage(), 500);
        }
    }

    public function provideFeedback($id): JsonResponse
    {
        try {
            $isHelpful = request('is_helpful');
            $feedback = request('feedback');

            $this->aiRepository->updateFeedback($id, $isHelpful, $feedback);

            return $this->successResponse(null, 'Feedback submitted');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to submit feedback: ' . $e->getMessage(), 500);
        }
    }

    private function generateWeeklyRecommendation(string $userId)
    {
        $user = $this->userRepository->find($userId);
        $startDate = now()->subDays(7);
        $endDate = now();
        $nutritionSummary = $this->foodRepository->getWeeklySummary($userId, $startDate, $endDate);
        $trainingSummary = $this->trainingRepository->getWeeklySummary($userId, $startDate, $endDate);

        $aiResponse = $this->aiService->generateNutritionRecommendations(
            $user,
            $nutritionSummary,
            $trainingSummary
        );

        $recommendation = $this->aiRepository->create([
            'user_id' => $userId,
            'period_start' => now()->startOfWeek(),
            'period_end' => now()->endOfWeek(),
            'period_type' => 'weekly',
            'summary' => $aiResponse['summary'],
            'recommendations' => $aiResponse['recommendations'],
            'nutrition_analysis' => $aiResponse['nutrition_analysis'] ?? null,
            'meal_suggestions' => $aiResponse['meal_suggestions'] ?? null,
            'training_suggestions' => $aiResponse['training_suggestions'] ?? null,
            'adherence_score' => $aiResponse['adherence_score'] ?? null,
            'ai_model' => $aiResponse['model_used'] ?? 'gemini',
            'raw_ai_response' => json_encode($aiResponse),
        ]);

        return new AIRecommendationResource($recommendation);
    }
}