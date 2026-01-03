<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Interfaces\Repositories\UserRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserController extends BaseController
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
        $this->middleware('auth:api');
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $userId = Auth::id();

            $user = $this->userRepository->updateProfile($userId, $request->validated());

            return $this->successResponse(
                new UserResource($user),
                'Profile updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update profile: ' . $e->getMessage(), 500);
        }
    }

    public function getStats(): JsonResponse
    {
        try {
            $userId = Auth::id();

            $stats = $this->userRepository->getUserStats($userId);

            return $this->successResponse($stats, 'User stats retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get stats: ' . $e->getMessage(), 500);
        }
    }

    public function getTodayProgress(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $date = now();

            $progress = $this->userRepository->getDailyProgress($userId, $date);

            return $this->successResponse($progress, 'Today\'s progress retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get progress: ' . $e->getMessage(), 500);
        }
    }

    public function getMostConsumedFoods(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $limit = request('limit', 5);

            $foods = $this->userRepository->getMostConsumedFoods($userId, $limit);

            return $this->successResponse($foods, 'Most consumed foods retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get foods: ' . $e->getMessage(), 500);
        }
    }
}