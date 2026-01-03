<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\TrainingLog\StoreTrainingLogRequest;
use App\Http\Resources\TrainingLogResource;
use App\Interfaces\Services\TrainingServiceInterface;
use App\Interfaces\Repositories\TrainingLogRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TrainingLogController extends BaseController
{
    public function __construct(
        private TrainingServiceInterface $trainingService,
        private TrainingLogRepositoryInterface $trainingRepository
    ) {
        $this->middleware('auth:api');
    }

    public function store(StoreTrainingLogRequest $request): JsonResponse
    {
        try {
            $userId = Auth::id();

            $result = $this->trainingService->logTrainingSession($userId, $request->validated());

            return $this->createdResponse(
                new TrainingLogResource($result['log']),
                'Training session logged successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to log training session: ' . $e->getMessage(), 500);
        }
    }

    public function index(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $date = request('date');

            $logs = $this->trainingRepository->getUserLogs($userId, $date ? \Carbon\Carbon::parse($date) : null);

            return $this->successResponse(
                TrainingLogResource::collection($logs),
                'Training logs retrieved'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get training logs: ' . $e->getMessage(), 500);
        }
    }

    public function getWeeklySummary(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $startDate = request('start_date', now()->startOfWeek()->format('Y-m-d'));
            $endDate = request('end_date', now()->endOfWeek()->format('Y-m-d'));

            $summary = $this->trainingRepository->getWeeklySummary(
                $userId,
                \Carbon\Carbon::parse($startDate),
                \Carbon\Carbon::parse($endDate)
            );

            return $this->successResponse($summary, 'Weekly training summary retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get weekly summary: ' . $e->getMessage(), 500);
        }
    }
}