<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AIRecommendationResource extends JsonResource
{
    public function toArray($request)
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
            'recommendations' => $this->getParsedRecommendations(),
            'meal_suggestions' => $this->getParsedMealSuggestions(),
            'training_suggestions' => $this->training_suggestions ? json_decode($this->training_suggestions, true) : null,
            'nutrition_analysis' => $this->nutrition_analysis ? json_decode($this->nutrition_analysis, true) : null,
            'adherence_score' => (float) $this->adherence_score,
            'overall_rating' => $this->overall_rating,
            'improvement_areas' => $this->improvement_areas,
            'ai_model' => $this->ai_model,
            'is_active' => (bool) $this->is_active,
            'is_helpful' => $this->is_helpful,
            'user_feedback' => $this->user_feedback,
            'viewed_at' => $this->viewed_at,
            'is_fallback' => (bool) $this->is_fallback,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Parse recommendations JSON
     */
    private function getParsedRecommendations(): array
    {
        try {
            if (is_string($this->recommendations)) {
                $parsed = json_decode($this->recommendations, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $parsed;
                }
            } elseif (is_array($this->recommendations)) {
                return $this->recommendations;
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return [
            'nutrition' => [],
            'training' => [],
            'goals' => []
        ];
    }

    /**
     * Parse meal suggestions JSON
     */
    private function getParsedMealSuggestions(): array
    {
        try {
            if (is_string($this->meal_suggestions)) {
                $parsed = json_decode($this->meal_suggestions, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $parsed;
                }
            } elseif (is_array($this->meal_suggestions)) {
                return $this->meal_suggestions;
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return [
            'breakfast' => '',
            'lunch' => '',
            'dinner' => '',
            'snacks' => []
        ];
    }

    /**
     * Get period label
     */
    private function getPeriodLabel(): string
    {
        $start = $this->period_start->format('M j');
        $end = $this->period_end->format('M j');

        return "Week of {$start} - {$end}";
    }
}
