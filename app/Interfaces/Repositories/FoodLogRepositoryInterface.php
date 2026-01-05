<?php

namespace App\Interfaces\Repositories;

use App\Models\FoodLog;
use Illuminate\Support\Collection;
use Carbon\Carbon;

interface FoodLogRepositoryInterface extends BaseRepositoryInterface
{
    public function getUserLogs(string $userId, ?Carbon $date = null): Collection;
    public function getDailySummary(string $userId, Carbon $date): array;
    public function getWeeklySummary(string $userId, Carbon $startDate, Carbon $endDate): array;
    public function getMonthlyTrend(string $userId, int $months = 3): array;
    public function getMostLoggedFoods(string $userId, int $limit = 5): array;
    public function getByMealType(string $userId, string $mealType, Carbon $date): Collection;
    public function getCalorieRange(string $userId, Carbon $date): array;
    public function getBatchNutritionData(string $userId, array $dates): array;
    public function search(string $userId, string $keyword, ?Carbon $startDate = null, ?Carbon $endDate = null): Collection;
    public function getAverageDailyIntake(string $userId, int $days = 7): array;
    public function getStatistics(string $userId): array;
    public function hasLoggedToday(string $userId): bool;
    public function getLastLoggedFood(string $userId): ?FoodLog;
}
