<?php

namespace App\Interfaces\Repositories;

use App\Models\FoodLog;
use Illuminate\Support\Collection;
use Carbon\Carbon;

interface FoodLogRepositoryInterface extends BaseRepositoryInterface
{
    public function getUserLogs(int $userId, ?Carbon $date = null): Collection;
    public function getDailySummary(int $userId, Carbon $date): array;
    public function getWeeklySummary(int $userId, Carbon $startDate, Carbon $endDate): array;
    public function getMonthlyTrend(int $userId, int $months = 3): array;
    public function getMostLoggedFoods(int $userId, int $limit = 5): array;
    public function getByMealType(int $userId, string $mealType, Carbon $date): Collection;
    public function getCalorieRange(int $userId, Carbon $date): array;
    public function getBatchNutritionData(int $userId, array $dates): array;
    public function search(int $userId, string $keyword, ?Carbon $startDate = null, ?Carbon $endDate = null): Collection;
    public function getAverageDailyIntake(int $userId, int $days = 7): array;
    public function getStatistics(int $userId): array;
    public function hasLoggedToday(int $userId): bool;
    public function getLastLoggedFood(int $userId): ?FoodLog;
}
