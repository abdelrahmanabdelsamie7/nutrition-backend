<?php

namespace App\Interfaces\Repositories;

use App\Models\AIRecommendation;
use Illuminate\Support\Collection;
use Carbon\Carbon;

interface AIRecommendationRepositoryInterface extends BaseRepositoryInterface
{
    public function getActiveRecommendations(int $userId): Collection;
    public function getLatestRecommendation(int $userId): ?AIRecommendation;
    public function getByPeriod(int $userId, Carbon $startDate, Carbon $endDate): Collection;
    public function getWeeklyRecommendations(int $userId, int $weeks = 4): Collection;
    public function existsForPeriod(int $userId, Carbon $startDate, Carbon $endDate): bool;
    public function markAsViewed(int $recommendationId): bool;
    public function updateFeedback(int $recommendationId, bool $isHelpful, ?string $feedback = null): bool;
    public function getRecommendationsNeedingFeedback(int $userId): Collection;
    public function getMostHelpfulRecommendations(int $userId, int $limit = 5): Collection;
    public function deactivateOldRecommendations(int $userId): int;
    public function getAIUsageStats(int $userId): array;
}