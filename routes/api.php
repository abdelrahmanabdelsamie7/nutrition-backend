<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    AuthController,
    FoodLogController,
    TrainingLogController,
    AIRecommendationController,
    UserController
};
/*
|--------------------------------------------------------------------------
|                              API Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);
});

Route::middleware(['auth:api'])->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });

    Route::prefix('food-logs')->group(function () {
        Route::post('text', [FoodLogController::class, 'storeText']);
        Route::post('voice', [FoodLogController::class, 'storeVoice']);
        Route::get('/', [FoodLogController::class, 'index']);
        Route::get('daily', [FoodLogController::class, 'getDaily']);
        Route::get('weekly', [FoodLogController::class, 'getWeeklySummary']);
    });

    Route::prefix('training-logs')->group(function () {
        Route::post('/', [TrainingLogController::class, 'store']);
        Route::get('/', [TrainingLogController::class, 'index']);
        Route::get('weekly', [TrainingLogController::class, 'getWeeklySummary']);
    });

    Route::prefix('ai-recommendations')->group(function () {
        Route::get('/', [AIRecommendationController::class, 'index']);
        Route::get('weekly', [AIRecommendationController::class, 'getWeekly']);
        Route::post('generate', [AIRecommendationController::class, 'generate']);
        Route::put('{id}/view', [AIRecommendationController::class, 'markAsViewed']);
        Route::post('{id}/feedback', [AIRecommendationController::class, 'provideFeedback']);
    });

    Route::prefix('user')->group(function () {
        Route::put('profile', [UserController::class, 'updateProfile']);
        Route::get('stats', [UserController::class, 'getStats']);
        Route::get('today-progress', [UserController::class, 'getTodayProgress']);
        Route::get('most-consumed-foods', [UserController::class, 'getMostConsumedFoods']);
    });
});