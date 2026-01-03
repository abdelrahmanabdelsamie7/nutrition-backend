<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AIRecommendationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,

            'period' => [
                'start' => $this->period_start,
                'end' => $this->period_end,
                'type' => $this->period_type,
                'label' => $this->getPeriodLabel(),
            ],

            'summary' => $this->summary,
            'recommendations' => $this->recommendations,
            'nutrition_analysis' => $this->nutrition_analysis,
            'meal_suggestions' => $this->meal_suggestions,
            'training_suggestions' => $this->training_suggestions,

            'adherence_score' => (float) $this->adherence_score,
            'overall_rating' => $this->overall_rating,
            'improvement_areas' => $this->improvement_areas,

            'ai_model' => $this->ai_model,
            'is_active' => $this->is_active,

            'is_helpful' => $this->is_helpful,
            'user_feedback' => $this->user_feedback,
            'viewed_at' => $this->viewed_at,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}