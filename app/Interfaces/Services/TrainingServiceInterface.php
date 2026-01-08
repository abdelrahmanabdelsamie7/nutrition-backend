<?php

namespace App\Interfaces\Services;

interface TrainingServiceInterface
{
    public function calculateCaloriesBurned(string $activity, int $duration, float $weight, float $intensity = 5.0): float;
    public function logTrainingSession(string $userId, array $data): array;
    public function getTrainingRecommendations(string $userId, array $goals): array;
    public function calculateTrainingVolume(array $sessions): array;
    public function getActivityMET(string $activity): float;
    public function suggestTrainingPlan(string $userId, string $goal, int $daysPerWeek): array;
}