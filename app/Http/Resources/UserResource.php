<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Lang;

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
                'gender' => $this->getTranslatedEnum('gender', $this->gender),
                'height' => $this->height,
                'weight' => $this->weight,
                'target_weight' => $this->target_weight,
                'activity_level' => $this->getTranslatedEnum('activity_level', $this->activity_level),
                'goal' => $this->getTranslatedEnum('goal', $this->goal),
                'diet_type' => $this->getTranslatedEnum('diet_type', $this->diet_type),
                'budget_level' => $this->getTranslatedEnum('budget_level', $this->budget_level),
                'bmr_formula' => $this->getTranslatedEnum('bmr_formula', $this->bmr_formula),
                'bmr' => $this->bmr,
                'tdee' => $this->tdee,
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
                'notification_frequency' => $this->getTranslatedEnum('notification_frequency', $this->notification_frequency),
            ],

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function getTranslatedEnum(string $type, ?string $value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        $translations = [
            'gender' => Lang::get('messages.genders'),
            'activity_level' => Lang::get('messages.activity_levels'),
            'goal' => Lang::get('messages.goals'),
            'diet_type' => Lang::get('messages.diet_types'),
            'budget_level' => Lang::get('messages.budget_levels'),
            'notification_frequency' => Lang::get('messages.notification_frequencies'),
            'bmr_formula' => Lang::get('messages.bmr_formulas'),
        ];

        return $translations[$type][$value] ?? $value;
    }
}