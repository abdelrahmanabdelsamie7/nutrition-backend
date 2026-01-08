<?php

return [

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'project_id' => env('GEMINI_PROJECT_ID'),
        'location' => env('GEMINI_LOCATION', 'us-central1'),
        'model_id' => env('GEMINI_MODEL_ID', 'gemini-2.0-flash-exp'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'calorie_ninjas' => [
        'api_key' => env('CALORIE_NINJAS_API_KEY'),
        'base_url' => 'https://api.calorieninjas.com/v1/',
        'timeout' => 10,
    ],

    'translation' => [
        'fallback_dictionary' => true,
        'use_google_translate' => true,
        'cache_translations' => true,
        'cache_ttl' => 86400,
    ],
];
