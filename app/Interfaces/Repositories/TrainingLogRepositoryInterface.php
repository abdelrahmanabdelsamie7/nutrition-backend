<?php

namespace App\Interfaces\Repositories;

use Illuminate\Support\Collection;
use Carbon\Carbon;

interface TrainingLogRepositoryInterface extends BaseRepositoryInterface
{
    public function getUserLogs(int $userId, ?Carbon $date = null): Collection;
    public function getDailySummary(int $userId, Carbon $date): array;
    public function getWeeklySummary(int $userId, Carbon $startDate, Carbon $endDate): array;
    public function getCaloriesByActivityType(int $userId, Carbon $startDate, Carbon $endDate): array;
    public function getMostFrequentActivities(int $userId, int $limit = 5): array;
    public function getTrainingConsistency(int $userId, int $weeks = 4): array;
    public function getTotalTrainingTime(int $userId, Carbon $startDate, Carbon $endDate): int;
    public function hasTrainedToday(int $userId): bool;
    public function getLastTrainingSession(int $userId): ?array;
    public function getTrainingStreak(int $userId): array;
}
