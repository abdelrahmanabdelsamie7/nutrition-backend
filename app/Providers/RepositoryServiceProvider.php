<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            \App\Interfaces\Repositories\UserRepositoryInterface::class,
            \App\Repositories\Eloquent\UserRepository::class
        );

        $this->app->bind(
            \App\Interfaces\Repositories\FoodLogRepositoryInterface::class,
            \App\Repositories\Eloquent\FoodLogRepository::class
        );

        $this->app->bind(
            \App\Interfaces\Repositories\TrainingLogRepositoryInterface::class,
            \App\Repositories\Eloquent\TrainingLogRepository::class
        );

        $this->app->bind(
            \App\Interfaces\Repositories\AIRecommendationRepositoryInterface::class,
            \App\Repositories\Eloquent\AIRecommendationRepository::class
        );

        $this->app->bind(
            \App\Interfaces\Repositories\NutritionCacheRepositoryInterface::class,
            \App\Repositories\Eloquent\NutritionCacheRepository::class
        );

        $this->app->bind(
            \App\Interfaces\Services\NutritionServiceInterface::class,
            \App\Services\Nutrition\NutritionService::class
        );

        $this->app->bind(
            \App\Interfaces\Services\VoiceServiceInterface::class,
            \App\Services\Voice\VoiceService::class
        );

        $this->app->bind(
            \App\Interfaces\Services\AIServiceInterface::class,
            \App\Services\AI\AIService::class
        );

        $this->app->bind(
            \App\Interfaces\Services\TrainingServiceInterface::class,
            \App\Services\Training\TrainingService::class
        );
    }

    public function boot(): void
    {
        //
    }
}