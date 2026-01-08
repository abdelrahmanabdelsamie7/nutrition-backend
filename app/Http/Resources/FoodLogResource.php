<?php

namespace App\Http\Resources;

use Illuminate\Support\Facades\Lang;
use Illuminate\Http\Resources\Json\JsonResource;

class FoodLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'raw_input' => $this->raw_input,

            'nutrition' => [
                'calories' => (float) $this->calories,
                'protein' => (float) $this->protein,
                'carbs' => (float) $this->carbs,
                'fat' => (float) $this->fat,
                'macros_percentage' => $this->getMacrosPercentage(),
            ],

            'source' => $this->source,
            'meal_type' => $this->getTranslateEnum('meal_type', $this->meal_type),
            'food_time' => $this->food_time,
            'is_cached' => $this->is_cached,

            'logged_at' => $this->logged_at,
            'created_at' => $this->created_at,

            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),
        ];
    }
    private function getTranslateEnum(string $type, ?string $value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        $translations = [
            'meal_type' => Lang::get('messages.meal_types'),
            'overall_rating' => Lang::get('messages.overall_rating'),
        ];

        return $translations[$type][$value] ?? $value;
    }
}
