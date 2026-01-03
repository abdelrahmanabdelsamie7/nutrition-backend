<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,

            'profile' => [
                'age' => $this->age,
                'gender' => $this->gender,
                'height' => $this->height,
                'weight' => $this->weight,
                'target_weight' => $this->target_weight,
                'activity_level' => $this->activity_level,
                'goal' => $this->goal,
                'diet_type' => $this->diet_type,
                'budget_level' => $this->budget_level,
            ],

            'daily_targets' => [
                'calories' => $this->daily_calorie_target,
                'protein' => $this->daily_protein_target,
                'carbs' => $this->daily_carbs_target,
                'fat' => $this->daily_fat_target,
            ],

            'settings' => [
                'voice_enabled' => $this->voice_enabled,
                'ai_recommendations_enabled' => $this->ai_recommendations_enabled,
                'notification_frequency' => $this->notification_frequency,
            ],
            
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}