<?php

namespace App\Http\Requests\FoodLog;

use Illuminate\Foundation\Http\FormRequest;

class StoreVoiceLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'audio' => 'required|file|mimes:mp3,wav,m4a,ogg|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'audio.max' => 'Audio file must not exceed 10MB.',
            'audio.mimes' => 'Supported audio formats: MP3, WAV, M4A, OGG.',
        ];
    }
}
