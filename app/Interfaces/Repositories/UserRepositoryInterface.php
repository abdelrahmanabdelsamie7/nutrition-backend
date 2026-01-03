<?php

namespace App\Interfaces\Repositories;

use App\Models\User;
use Illuminate\Support\Collection;
use Carbon\Carbon;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function findByEmail(string $email): ?User;
    public function updateProfile(int $userId, array $data): User;
    public function getUserStats(int $userId): array;
    public function getUsersWithNutritionSummary(array $filters = [], int $perPage = 20): array;
    public function getDailyProgress(int $userId, Carbon $date): array;
    public function getWeeklySummary(int $userId, Carbon $startDate, Carbon $endDate): array;
    public function getRemainingCalories(int $userId): float;
    public function getMostConsumedFoods(int $userId, int $limit = 5): array;
    public function getUsersByActivityLevel(string $activityLevel): Collection;
    public function getInactiveUsers(int $days = 7): Collection;
    public function updateDailyTargets(int $userId, array $targets): bool;
    public function getUsersNeedingRecommendations(): Collection;
}