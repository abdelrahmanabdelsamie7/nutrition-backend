<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\FoodLog\StoreFoodLogRequest;
use App\Http\Requests\FoodLog\StoreVoiceLogRequest;
use App\Http\Resources\FoodLogResource;
use App\Interfaces\Services\NutritionServiceInterface;
use App\Interfaces\Repositories\FoodLogRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FoodLogController extends BaseController
{
    public function __construct(
        private NutritionServiceInterface $nutritionService,
        private FoodLogRepositoryInterface $foodLogRepository
    ) {
        $this->middleware('auth:api');
    }

    public function storeText(StoreFoodLogRequest $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $text = $request->text;

            $nutritionData = $this->nutritionService->processTextInput($text, $userId);

            return $this->createdResponse($nutritionData, 'Food logged successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to log food: ' . $e->getMessage(), 500);
        }
    }

    public function storeVoice(StoreVoiceLogRequest $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $audioFile = $request->file('audio');

            $nutritionData = $this->nutritionService->processVoiceInput($audioFile, $userId);

            return $this->createdResponse($nutritionData, 'Voice food log processed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to process voice input: ' . $e->getMessage(), 500);
        }
    }

    public function getDaily(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $date = request('date', now()->format('Y-m-d'));

            $summary = $this->nutritionService->getDailySummary($userId, $date);

            return $this->successResponse($summary, 'Daily summary retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get daily summary: ' . $e->getMessage(), 500);
        }
    }

    public function getWeeklySummary(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $startDate = request('start_date', now()->startOfWeek()->format('Y-m-d'));
            $endDate = request('end_date', now()->endOfWeek()->format('Y-m-d'));

            $summary = $this->nutritionService->getWeeklySummary($userId, $startDate, $endDate);

            return $this->successResponse($summary, 'Weekly summary retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get weekly summary: ' . $e->getMessage(), 500);
        }
    }

    public function index(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $date = request('date');

            $logs = $this->foodLogRepository->getUserLogs($userId, $date ? \Carbon\Carbon::parse($date) : null);

            return $this->successResponse(FoodLogResource::collection($logs), 'Food logs retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get food logs: ' . $e->getMessage(), 500);
        }
    }
}
