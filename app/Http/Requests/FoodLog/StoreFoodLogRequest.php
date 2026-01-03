<?php

namespace App\Http\Requests\FoodLog;

use Illuminate\Foundation\Http\FormRequest;

class StoreFoodLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => 'required|string|min:2|max:500',
            'meal_type' => 'nullable|in:breakfast,lunch,dinner,snack',
            'food_time' => 'nullable|string',
            'logged_at' => 'nullable|date',
        ];
    }
}