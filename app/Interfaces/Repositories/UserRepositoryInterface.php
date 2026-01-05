<?php

namespace App\Interfaces\Repositories;

use App\Models\User;
use Illuminate\Support\Collection;
use Carbon\Carbon;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function findByEmail(string $email): ?User;
    public function updateProfile(string $userId, array $data): User;
    public function getUserStats(string $userId): array;
    public function getUsersWithNutritionSummary(array $filters = [], int $perPage = 20): array;
    public function getDailyProgress(string $userId, Carbon $date): array;
    public function getWeeklySummary(string $userId, Carbon $startDate, Carbon $endDate): array;
    public function getRemainingCalories(string $userId): float;
    public function getMostConsumedFoods(string $userId, int $limit = 5): array;
    public function getUsersByActivityLevel(string $activityLevel): Collection;
    public function getInactiveUsers(int $days = 7): Collection;
    public function updateDailyTargets(string $userId, array $targets): bool;
    public function getUsersNeedingRecommendations(): Collection;
}
