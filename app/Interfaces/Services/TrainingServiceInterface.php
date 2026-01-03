<?php

namespace App\Interfaces\Services;

interface TrainingServiceInterface
{
    public function calculateCaloriesBurned(string $activity, int $duration, float $weight, float $intensity = 5.0): float;
    public function logTrainingSession(int $userId, array $data): array;
    public function getTrainingRecommendations(int $userId, array $goals): array;
    public function calculateTrainingVolume(array $sessions): array;
    public function getActivityMET(string $activity): float;
    public function suggestTrainingPlan(int $userId, string $goal, int $daysPerWeek): array;
}