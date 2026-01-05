<?php

namespace App\Interfaces\Repositories;

use Illuminate\Support\Collection;
use Carbon\Carbon;

interface TrainingLogRepositoryInterface extends BaseRepositoryInterface
{
    public function getUserLogs(string $userId, ?Carbon $date = null): Collection;
    public function getDailySummary(string $userId, Carbon $date): array;
    public function getWeeklySummary(string $userId, Carbon $startDate, Carbon $endDate): array;
    public function getCaloriesByActivityType(string $userId, Carbon $startDate, Carbon $endDate): array;
    public function getMostFrequentActivities(string $userId, int $limit = 5): array;
    public function getTrainingConsistency(string $userId, int $weeks = 4): array;
    public function getTotalTrainingTime(string $userId, Carbon $startDate, Carbon $endDate): int;
    public function hasTrainedToday(string $userId): bool;
    public function getLastTrainingSession(string $userId): ?array;
    public function getTrainingStreak(string $userId): array;
}
