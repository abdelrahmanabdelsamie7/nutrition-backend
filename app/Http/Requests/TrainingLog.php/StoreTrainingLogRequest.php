<?php

namespace App\Http\Requests\TrainingLog;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrainingLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activity_name' => 'required|string|max:255',
            'activity_type' => 'nullable|in:cardio,strength,flexibility,sports,hiit,other',
            'duration' => 'required|integer|min:1|max:480',
            'intensity_level' => 'nullable|numeric|min:1|max:10',
            'reps' => 'nullable|integer|min:1',
            'sets' => 'nullable|integer|min:1',
            'weight_used' => 'nullable|numeric|min:0.1',
            'distance' => 'nullable|numeric|min:0.1',
            'heart_rate_avg' => 'nullable|integer|min:40|max:220',
            'notes' => 'nullable|string|max:1000',
            'performed_at' => 'nullable|date',
        ];
    }
}
