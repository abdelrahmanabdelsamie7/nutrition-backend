<?php

namespace App\Http\Resources;

use Illuminate\Support\Facades\Lang;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainingLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'activity_name' => $this->activity_name,
            'activity_type' => $this->activity_type,

            'duration' => $this->duration,
            'intensity_level' => (float) $this->getTranslatedEnum('intensity_level', $this->intensity_level),
            'intensity_description' => $this->getIntensityDescription(),

            'estimated_calories_burned' => (float) $this->estimated_calories_burned,
            'calories_per_minute' => $this->getCaloriesPerMinute(),

            'reps' => $this->reps,
            'sets' => $this->sets,
            'weight_used' => $this->weight_used,
            'distance' => $this->distance,
            'heart_rate_avg' => $this->heart_rate_avg,

            'notes' => $this->notes,

            'performed_at' => $this->performed_at,
            'created_at' => $this->created_at,
        ];
    }
    private function getTranslatedEnum(string $type, ?string $value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        $translations = [
            'intensity_level' => Lang::get('messages.intensity_levels'),
            'overall_rating' => Lang::get('messages.overall_rating'),
        ];

        return $translations[$type][$value] ?? $value;
    }
}
