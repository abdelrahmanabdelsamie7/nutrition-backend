<?php

namespace App\DTOs;

use Carbon\Carbon;

class AIRecommendationDTO
{
    public function __construct(
        public int $userId,
        public Carbon $periodStart,
        public Carbon $periodEnd,
        public string $periodType,
        public string $summary,
        public array $recommendations,
        public ?array $nutritionAnalysis = null,
        public ?array $mealSuggestions = null,
        public ?array $trainingSuggestions = null,
        public ?float $adherenceScore = null,
        public ?string $overallRating = null,
        public ?array $improvementAreas = null,
        public ?string $aiModel = 'gemini-flash-2.0',
        public ?array $aiParameters = null,
        public ?array $promptUsed = null,
        public ?string $rawAIResponse = null,
        public bool $isActive = true
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['user_id'],
            periodStart: Carbon::parse($data['period_start']),
            periodEnd: Carbon::parse($data['period_end']),
            periodType: $data['period_type'] ?? 'weekly',
            summary: $data['summary'],
            recommendations: $data['recommendations'] ?? [],
            nutritionAnalysis: $data['nutrition_analysis'] ?? null,
            mealSuggestions: $data['meal_suggestions'] ?? null,
            trainingSuggestions: $data['training_suggestions'] ?? null,
            adherenceScore: $data['adherence_score'] ?? null,
            overallRating: $data['overall_rating'] ?? null,
            improvementAreas: $data['improvement_areas'] ?? null,
            aiModel: $data['ai_model'] ?? 'gemini-flash-2.0',
            aiParameters: $data['ai_parameters'] ?? null,
            promptUsed: $data['prompt_used'] ?? null,
            rawAIResponse: $data['raw_ai_response'] ?? null,
            isActive: $data['is_active'] ?? true
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'period_start' => $this->periodStart->format('Y-m-d'),
            'period_end' => $this->periodEnd->format('Y-m-d'),
            'period_type' => $this->periodType,
            'summary' => $this->summary,
            'recommendations' => $this->recommendations,
            'nutrition_analysis' => $this->nutritionAnalysis,
            'meal_suggestions' => $this->mealSuggestions,
            'training_suggestions' => $this->trainingSuggestions,
            'adherence_score' => $this->adherenceScore,
            'overall_rating' => $this->overallRating,
            'improvement_areas' => $this->improvementAreas,
            'ai_model' => $this->aiModel,
            'ai_parameters' => $this->aiParameters,
            'prompt_used' => $this->promptUsed,
            'raw_ai_response' => $this->rawAIResponse,
            'is_active' => $this->isActive,
        ];
    }

    public function isValid(): bool
    {
        return !empty($this->summary) &&
            !empty($this->recommendations) &&
            $this->periodStart < $this->periodEnd;
    }
}