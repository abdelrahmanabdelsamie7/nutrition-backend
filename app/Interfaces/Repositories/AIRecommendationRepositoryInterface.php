<?php

namespace App\Interfaces\Repositories;

use App\Models\AIRecommendation;
use Illuminate\Support\Collection;
use Carbon\Carbon;

interface AIRecommendationRepositoryInterface extends BaseRepositoryInterface
{
    public function getActiveRecommendations(string $userId): Collection;
    public function getLatestRecommendation(string $userId): ?AIRecommendation;
    public function getByPeriod(string $userId, Carbon $startDate, Carbon $endDate): Collection;
    public function getWeeklyRecommendations(string $userId, int $weeks = 4): Collection;
    public function existsForPeriod(string $userId, Carbon $startDate, Carbon $endDate): bool;
    public function markAsViewed(int $recommendationId): bool;
    public function updateFeedback(int $recommendationId, bool $isHelpful, ?string $feedback = null): bool;
    public function getRecommendationsNeedingFeedback(string $userId): Collection;
    public function getMostHelpfulRecommendations(string $userId, int $limit = 5): Collection;
    public function deactivateOldRecommendations(string $userId): int;
    public function getAIUsageStats(string $userId): array;
}
