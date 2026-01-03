<?php

namespace App\Interfaces\Services;

use App\Models\User;

interface AIServiceInterface
{
    public function generateNutritionRecommendations(User $user, array $nutritionSummary, array $trainingSummary);
    public function generateMealSuggestions(User $user, array $nutritionData, array $budgetConstraints): array;
    public function generateTrainingRecommendations(User $user, array $trainingHistory): array;
    public function analyzeNutritionPatterns(array $nutritionData): array;
    public function getAIResponse(string $prompt, array $parameters = []): string;
    public function parseAIResponse(string $response): array;
    public function validateAIResponse(array $response): bool;
    public function getAvailableModels(): array;
}
