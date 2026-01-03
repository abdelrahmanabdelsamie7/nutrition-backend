<?php

namespace App\Http\Resources;

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
            'meal_type' => $this->meal_type,
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
}
