<?php

namespace App\Repositories\Eloquent;

use App\Interfaces\Repositories\AIRecommendationRepositoryInterface;
use App\Models\AIRecommendation;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AIRecommendationRepository extends BaseRepository implements AIRecommendationRepositoryInterface
{
    public function __construct(AIRecommendation $model)
    {
        parent::__construct($model);
    }

    public function getActiveRecommendations(string $userId): Collection
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getLatestRecommendation(string $userId): ?AIRecommendation
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function getByPeriod(string $userId, Carbon $startDate, Carbon $endDate): Collection
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('period_start', '>=', $startDate)
            ->where('period_end', '<=', $endDate)
            ->orderBy('period_start', 'desc')
            ->get();
    }

    public function getWeeklyRecommendations(string $userId, int $weeks = 4): Collection
    {
        $startDate = now()->subWeeks($weeks)->startOfWeek();

        return $this->model
            ->where('user_id', $userId)
            ->where('period_type', 'weekly')
            ->where('period_start', '>=', $startDate)
            ->orderBy('period_start', 'desc')
            ->get();
    }

    public function existsForPeriod(string $userId, Carbon $startDate, Carbon $endDate): bool
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('period_start', $startDate)
            ->where('period_end', $endDate)
            ->exists();
    }

    public function markAsViewed(int $recommendationId): bool
    {
        $recommendation = $this->findOrFail($recommendationId);

        if (!$recommendation->viewed_at) {
            $recommendation->viewed_at = now();
            return $recommendation->save();
        }

        return true;
    }

    public function updateFeedback(int $recommendationId, bool $isHelpful, ?string $feedback = null): bool
    {
        $recommendation = $this->findOrFail($recommendationId);

        $recommendation->update([
            'is_helpful' => $isHelpful,
            'user_feedback' => $feedback,
        ]);

        return true;
    }

    public function getRecommendationsNeedingFeedback(string $userId): Collection
    {
        return $this->model
            ->where('user_id', $userId)
            ->whereNull('is_helpful')
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getMostHelpfulRecommendations(string $userId, int $limit = 5): Collection
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('is_helpful', true)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function deactivateOldRecommendations(string $userId): int
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('period_end', '<', now()->subDays(14))
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    public function getAIUsageStats(string $userId): array
    {
        $cacheKey = "user_{$userId}_ai_usage_stats";

        return Cache::remember($cacheKey, 3600, function () use ($userId) {
            $total = $this->model->where('user_id', $userId)->count();
            $helpful = $this->model->where('user_id', $userId)->where('is_helpful', true)->count();
            $notHelpful = $this->model->where('user_id', $userId)->where('is_helpful', false)->count();
            $noFeedback = $this->model->where('user_id', $userId)->whereNull('is_helpful')->count();

            $modelBreakdown = DB::table('ai_recommendations')
                ->select(
                    'ai_model',
                    DB::raw('COUNT(*) as count'),
                    DB::raw('AVG(adherence_score) as avg_score')
                )
                ->where('user_id', $userId)
                ->groupBy('ai_model')
                ->get()
                ->toArray();

            $recentActivity = $this->model
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'period_type', 'created_at', 'adherence_score', 'is_helpful'])
                ->toArray();

            return [
                'total_recommendations' => $total,
                'feedback_stats' => [
                    'helpful' => $helpful,
                    'not_helpful' => $notHelpful,
                    'no_feedback' => $noFeedback,
                    'helpful_percentage' => $total > 0 ? ($helpful / $total) * 100 : 0,
                ],
                'model_breakdown' => $modelBreakdown,
                'recent_activity' => $recentActivity,
                'avg_adherence_score' => $this->model->where('user_id', $userId)->avg('adherence_score'),
            ];
        });
    }
}
