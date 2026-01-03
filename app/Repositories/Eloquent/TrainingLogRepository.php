<?php

namespace App\Repositories\Eloquent;

use App\Interfaces\Repositories\TrainingLogRepositoryInterface;
use App\Models\TrainingLog;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class TrainingLogRepository extends BaseRepository implements TrainingLogRepositoryInterface
{
    public function __construct(TrainingLog $model)
    {
        parent::__construct($model);
    }

    public function getUserLogs(int $userId, ?Carbon $date = null): Collection
    {
        $query = $this->model->where('user_id', $userId);

        if ($date) {
            $query->whereDate('performed_at', $date);
        }

        return $query->orderBy('performed_at', 'desc')->get();
    }

    public function getDailySummary(int $userId, Carbon $date): array
    {
        return DB::table('training_logs')
            ->select(
                DB::raw('SUM(estimated_calories_burned) as total_calories_burned'),
                DB::raw('SUM(duration) as total_duration'),
                DB::raw('COUNT(*) as session_count'),
                DB::raw('AVG(intensity_level) as avg_intensity'),
                DB::raw('GROUP_CONCAT(DISTINCT activity_type) as activity_types')
            )
            ->where('user_id', $userId)
            ->whereDate('performed_at', $date)
            ->first()
            ?->toArray() ?? [
                'total_calories_burned' => 0,
                'total_duration' => 0,
                'session_count' => 0,
                'avg_intensity' => 0,
                'activity_types' => ''
            ];
    }

    public function getWeeklySummary(int $userId, Carbon $startDate, Carbon $endDate): array
    {
        $cacheKey = "user_{$userId}_training_weekly_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}";

        return Cache::remember($cacheKey, 1800, function () use ($userId, $startDate, $endDate) {
            $dailyData = DB::table('training_logs')
                ->select(
                    DB::raw('DATE(performed_at) as session_date'),
                    DB::raw('SUM(estimated_calories_burned) as daily_calories_burned'),
                    DB::raw('SUM(duration) as daily_duration'),
                    DB::raw('COUNT(*) as daily_sessions')
                )
                ->where('user_id', $userId)
                ->whereBetween('performed_at', [$startDate, $endDate])
                ->groupBy(DB::raw('DATE(performed_at)'))
                ->orderBy('session_date')
                ->get();

            $totals = DB::table('training_logs')
                ->select(
                    DB::raw('SUM(estimated_calories_burned) as total_calories_burned'),
                    DB::raw('SUM(duration) as total_duration'),
                    DB::raw('COUNT(*) as total_sessions'),
                    DB::raw('AVG(duration) as avg_session_duration')
                )
                ->where('user_id', $userId)
                ->whereBetween('performed_at', [$startDate, $endDate])
                ->first();

            $activityBreakdown = DB::table('training_logs')
                ->select(
                    'activity_type',
                    DB::raw('COUNT(*) as session_count'),
                    DB::raw('SUM(duration) as total_duration'),
                    DB::raw('SUM(estimated_calories_burned) as total_calories')
                )
                ->where('user_id', $userId)
                ->whereBetween('performed_at', [$startDate, $endDate])
                ->groupBy('activity_type')
                ->get()
                ->toArray();

            return [
                'daily_data' => $dailyData->toArray(),
                'totals' => (array) $totals,
                'activity_breakdown' => $activityBreakdown,
                'days_trained' => $dailyData->count(),
            ];
        });
    }

    public function getCaloriesByActivityType(int $userId, Carbon $startDate, Carbon $endDate): array
    {
        return DB::table('training_logs')
            ->select(
                'activity_type',
                DB::raw('SUM(estimated_calories_burned) as total_calories'),
                DB::raw('AVG(estimated_calories_burned) as avg_calories_per_session'),
                DB::raw('COUNT(*) as session_count')
            )
            ->where('user_id', $userId)
            ->whereBetween('performed_at', [$startDate, $endDate])
            ->groupBy('activity_type')
            ->orderByDesc('total_calories')
            ->get()
            ->map(function ($item) {
                return [
                    'activity_type' => $item->activity_type,
                    'total_calories' => (float) $item->total_calories,
                    'avg_calories_per_session' => (float) $item->avg_calories_per_session,
                    'session_count' => (int) $item->session_count,
                ];
            })
            ->toArray();
    }

    public function getMostFrequentActivities(int $userId, int $limit = 5): array
    {
        return DB::table('training_logs')
            ->select(
                'activity_name',
                'activity_type',
                DB::raw('COUNT(*) as frequency'),
                DB::raw('SUM(duration) as total_duration'),
                DB::raw('AVG(duration) as avg_duration')
            )
            ->where('user_id', $userId)
            ->groupBy('activity_name', 'activity_type')
            ->orderByDesc('frequency')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getTrainingConsistency(int $userId, int $weeks = 4): array
    {
        $startDate = now()->subWeeks($weeks);

        $weeklyData = DB::table('training_logs')
            ->select(
                DB::raw('WEEK(performed_at) as week_number'),
                DB::raw('COUNT(*) as session_count'),
                DB::raw('SUM(duration) as total_duration'),
                DB::raw('SUM(estimated_calories_burned) as total_calories')
            )
            ->where('user_id', $userId)
            ->where('performed_at', '>=', $startDate)
            ->groupBy(DB::raw('WEEK(performed_at)'))
            ->orderBy('week_number')
            ->get();

        $targetSessionsPerWeek = 3; // Example target

        return [
            'weeks_analyzed' => $weeks,
            'weekly_data' => $weeklyData->toArray(),
            'total_sessions' => $weeklyData->sum('session_count'),
            'avg_sessions_per_week' => $weeklyData->avg('session_count'),
            'consistency_score' => ($weeklyData->count() / $weeks) * 100,
            'target_achievement' => ($weeklyData->sum('session_count') / ($targetSessionsPerWeek * $weeks)) * 100,
        ];
    }

    public function getTotalTrainingTime(int $userId, Carbon $startDate, Carbon $endDate): int
    {
        return (int) DB::table('training_logs')
            ->where('user_id', $userId)
            ->whereBetween('performed_at', [$startDate, $endDate])
            ->sum('duration');
    }

    public function hasTrainedToday(int $userId): bool
    {
        return $this->model
            ->where('user_id', $userId)
            ->whereDate('performed_at', today())
            ->exists();
    }

    public function getLastTrainingSession(int $userId): ?array
    {
        $session = $this->model
            ->where('user_id', $userId)
            ->orderBy('performed_at', 'desc')
            ->first();

        return $session ? $session->toArray() : null;
    }

    public function getTrainingStreak(int $userId): array
    {
        $trainingDays = DB::table('training_logs')
            ->select(DB::raw('DATE(performed_at) as training_date'))
            ->where('user_id', $userId)
            ->groupBy(DB::raw('DATE(performed_at)'))
            ->orderBy('training_date', 'desc')
            ->pluck('training_date')
            ->map(function ($date) {
                return Carbon::parse($date);
            });

        if ($trainingDays->isEmpty()) {
            return [
                'current_streak' => 0,
                'max_streak' => 0,
                'last_trained' => null,
            ];
        }

        $currentStreak = 0;
        $maxStreak = 0;
        $tempStreak = 1;
        $lastDate = $trainingDays[0];

        // Check current streak
        $checkDate = $lastDate->copy();
        $foundStreak = true;

        while ($foundStreak) {
            if ($trainingDays->contains(function ($date) use ($checkDate) {
                return $date->isSameDay($checkDate);
            })) {
                $currentStreak++;
                $checkDate->subDay();
            } else {
                $foundStreak = false;
            }
        }

        // Calculate max streak
        for ($i = 1; $i < $trainingDays->count(); $i++) {
            $diff = $trainingDays[$i]->diffInDays($trainingDays[$i - 1]);

            if ($diff === 1) {
                $tempStreak++;
                $maxStreak = max($maxStreak, $tempStreak);
            } else {
                $tempStreak = 1;
            }
        }

        return [
            'current_streak' => $currentStreak,
            'max_streak' => $maxStreak,
            'last_trained' => $lastDate->format('Y-m-d'),
            'total_training_days' => $trainingDays->count(),
        ];
    }
}