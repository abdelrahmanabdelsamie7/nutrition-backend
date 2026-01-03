<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',

            'age' => 'nullable|integer|min:15|max:100',
            'gender' => 'nullable|in:male,female',
            'height' => 'nullable|numeric|min:100|max:250',
            'weight' => 'nullable|numeric|min:30|max:300',
            'activity_level' => 'nullable|in:sedentary,light,moderate,active,very_active',
            'goal' => 'nullable|in:lose_weight,maintain,gain_weight,build_muscle',
            'diet_type' => 'nullable|in:balanced,keto,vegetarian,vegan,mediterranean',
            'budget_level' => 'nullable|in:low,medium,high',
        ];
    }

    public function messages(): array
    {
        return [
            'password.confirmed' => 'Password confirmation does not match.',
            'email.unique' => 'This email is already registered.',
        ];
    }
}
