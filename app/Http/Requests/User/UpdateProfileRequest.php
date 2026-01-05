<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = auth()->id();

        return [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $userId,

            "age" => "nullable|integer|min:15|max:100",
            "gender" => "nullable|in:male,female",
            "height" => "nullable|numeric|min:100|max:250",
            "weight" => "nullable|numeric|min:30|max:300",
            "target_weight" => "nullable|numeric|min:30|max:300",
            "activity_level" => "nullable|in:sedentary,light,moderate,active,very_active",
            "goal" => "nullable|in:lose_weight,maintain,gain_weight,build_muscle",
            "diet_type" => "nullable|in:balanced,keto,vegetarian,vegan,mediterranean",
            "budget_level" => "nullable|in:low,medium,high",

            "voice_enabled" => "nullable|boolean",
            "ai_recommendations_enabled" => "nullable|boolean",
            "notification_frequency" => "nullable|in:never,daily,weekly",
        ];
    }
}
