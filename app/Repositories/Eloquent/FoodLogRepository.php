<?php

namespace App\Repositories\Eloquent;

use App\Interfaces\Repositories\FoodLogRepositoryInterface;
use App\Models\FoodLog;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class FoodLogRepository extends BaseRepository implements FoodLogRepositoryInterface
{
    public function __construct(FoodLog $model)
    {
        parent::__construct($model);
    }

    public function getUserLogs(int $userId, ?Carbon $date = null): Collection
    {
        $query = $this->model->where('user_id', $userId);

        if ($date) {
            $query->whereDate('logged_at', $date);
        }

        return $query->orderBy('logged_at', 'desc')->get();
    }

    public function getDailySummary(int $userId, Carbon $date): array
    {
        $cacheKey = "user_{$userId}_daily_summary_{$date->format('Y-m-d')}";

        return Cache::remember($cacheKey, 300, function () use ($userId, $date) {
            return DB::table('food_logs')
                ->select(
                    DB::raw('SUM(calories) as total_calories'),
                    DB::raw('SUM(protein) as total_protein'),
                    DB::raw('SUM(carbs) as total_carbs'),
                    DB::raw('SUM(fat) as total_fat'),
                    DB::raw('COUNT(*) as meal_count'),
                    DB::raw('AVG(calories) as avg_meal_calories'),
                    DB::raw('GROUP_CONCAT(DISTINCT meal_type) as meal_types')
                )
                ->where('user_id', $userId)
                ->whereDate('logged_at', $date)
                ->first()
                ?->toArray() ?? [
                    'total_calories' => 0,
                    'total_protein' => 0,
                    'total_carbs' => 0,
                    'total_fat' => 0,
                    'meal_count' => 0,
                    'avg_meal_calories' => 0,
                    'meal_types' => ''
                ];
        });
    }

    public function getWeeklySummary(int $userId, Carbon $startDate, Carbon $endDate): array
    {
        $cacheKey = "user_{$userId}_weekly_summary_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}";

        return Cache::remember($cacheKey, 1800, function () use ($userId, $startDate, $endDate) {
            $dailyData = DB::table('food_logs')
                ->select(
                    DB::raw('DATE(logged_at) as log_date'),
                    DB::raw('SUM(calories) as daily_calories'),
                    DB::raw('SUM(protein) as daily_protein'),
                    DB::raw('SUM(carbs) as daily_carbs'),
                    DB::raw('SUM(fat) as daily_fat'),
                    DB::raw('COUNT(*) as daily_meals')
                )
                ->where('user_id', $userId)
                ->whereBetween('logged_at', [$startDate, $endDate])
                ->groupBy(DB::raw('DATE(logged_at)'))
                ->orderBy('log_date')
                ->get();

            $totals = DB::table('food_logs')
                ->select(
                    DB::raw('SUM(calories) as total_calories'),
                    DB::raw('SUM(protein) as total_protein'),
                    DB::raw('SUM(carbs) as total_carbs'),
                    DB::raw('SUM(fat) as total_fat'),
                    DB::raw('COUNT(*) as total_meals')
                )
                ->where('user_id', $userId)
                ->whereBetween('logged_at', [$startDate, $endDate])
                ->first();

            return [
                'daily_data' => $dailyData->toArray(),
                'totals' => (array) $totals,
                'averages' => [
                    'daily_calories' => $dailyData->count() > 0 ? $dailyData->avg('daily_calories') : 0,
                    'daily_protein' => $dailyData->count() > 0 ? $dailyData->avg('daily_protein') : 0,
                    'daily_carbs' => $dailyData->count() > 0 ? $dailyData->avg('daily_carbs') : 0,
                    'daily_fat' => $dailyData->count() > 0 ? $dailyData->avg('daily_fat') : 0,
                ],
                'days_logged' => $dailyData->count(),
            ];
        });
    }

    public function getMonthlyTrend(int $userId, int $months = 3): array
    {
        $startDate = now()->subMonths($months)->startOfMonth();

        return DB::table('food_logs')
            ->select(
                DB::raw('DATE_FORMAT(logged_at, "%Y-%m") as month'),
                DB::raw('SUM(calories) as monthly_calories'),
                DB::raw('AVG(calories) as avg_daily_calories'),
                DB::raw('COUNT(*) as total_logs'),
                DB::raw('COUNT(DISTINCT DATE(logged_at)) as days_logged')
            )
            ->where('user_id', $userId)
            ->where('logged_at', '>=', $startDate)
            ->groupBy(DB::raw('DATE_FORMAT(logged_at, "%Y-%m")'))
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => $item->month,
                    'monthly_calories' => (float) $item->monthly_calories,
                    'avg_daily_calories' => (float) $item->avg_daily_calories,
                    'total_logs' => (int) $item->total_logs,
                    'days_logged' => (int) $item->days_logged,
                    'avg_logs_per_day' => $item->days_logged > 0 ? $item->total_logs / $item->days_logged : 0,
                ];
            })
            ->toArray();
    }

    public function getMostLoggedFoods(int $userId, int $limit = 5): array
    {
        $cacheKey = "user_{$userId}_most_logged_foods_{$limit}";

        return Cache::remember($cacheKey, 3600, function () use ($userId, $limit) {
            return DB::table('food_logs')
                ->select(
                    DB::raw('LOWER(TRIM(raw_input)) as food_item'),
                    DB::raw('COUNT(*) as log_count'),
                    DB::raw('AVG(calories) as avg_calories'),
                    DB::raw('SUM(calories) as total_calories'),
                    DB::raw('AVG(protein) as avg_protein'),
                    DB::raw('AVG(carbs) as avg_carbs'),
                    DB::raw('AVG(fat) as avg_fat')
                )
                ->where('user_id', $userId)
                ->whereNotNull('raw_input')
                ->where('raw_input', '!=', '')
                ->groupBy(DB::raw('LOWER(TRIM(raw_input))'))
                ->orderByDesc('log_count')
                ->limit($limit)
                ->get()
                ->map(function ($item) {
                    return [
                        'food_item' => ucfirst($item->food_item),
                        'log_count' => (int) $item->log_count,
                        'avg_calories' => (float) $item->avg_calories,
                        'total_calories' => (float) $item->total_calories,
                        'avg_macros' => [
                            'protein' => (float) $item->avg_protein,
                            'carbs' => (float) $item->avg_carbs,
                            'fat' => (float) $item->avg_fat,
                        ]
                    ];
                })
                ->toArray();
        });
    }

    public function getByMealType(int $userId, string $mealType, Carbon $date): Collection
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('meal_type', $mealType)
            ->whereDate('logged_at', $date)
            ->orderBy('logged_at')
            ->get();
    }

    public function getCalorieRange(int $userId, Carbon $date): array
    {
        $stats = DB::table('food_logs')
            ->select(
                DB::raw('MIN(calories) as min_calories'),
                DB::raw('MAX(calories) as max_calories'),
                DB::raw('AVG(calories) as avg_calories'),
                DB::raw('PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY calories) as median_calories')
            )
            ->where('user_id', $userId)
            ->whereDate('logged_at', $date)
            ->first();

        return [
            'min' => (float) ($stats->min_calories ?? 0),
            'max' => (float) ($stats->max_calories ?? 0),
            'avg' => (float) ($stats->avg_calories ?? 0),
            'median' => (float) ($stats->median_calories ?? 0),
        ];
    }

    public function getBatchNutritionData(int $userId, array $dates): array
    {
        $formattedDates = array_map(function ($date) {
            return Carbon::parse($date)->format('Y-m-d');
        }, $dates);

        return DB::table('food_logs')
            ->select(
                DB::raw('DATE(logged_at) as log_date'),
                DB::raw('SUM(calories) as total_calories'),
                DB::raw('SUM(protein) as total_protein'),
                DB::raw('SUM(carbs) as total_carbs'),
                DB::raw('SUM(fat) as total_fat'),
                DB::raw('COUNT(*) as meal_count')
            )
            ->where('user_id', $userId)
            ->whereIn(DB::raw('DATE(logged_at)'), $formattedDates)
            ->groupBy(DB::raw('DATE(logged_at)'))
            ->get()
            ->keyBy('log_date')
            ->toArray();
    }

    public function search(int $userId, string $keyword, ?Carbon $startDate = null, ?Carbon $endDate = null): Collection
    {
        $query = $this->model->where('user_id', $userId);

        $query->where(function ($q) use ($keyword) {
            $q->where('raw_input', 'like', "%{$keyword}%")
                ->orWhere('meal_type', 'like', "%{$keyword}%");
        });

        if ($startDate) {
            $query->where('logged_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('logged_at', '<=', $endDate);
        }

        return $query->orderBy('logged_at', 'desc')->get();
    }

    public function getAverageDailyIntake(int $userId, int $days = 7): array
    {
        $startDate = now()->subDays($days);

        $stats = DB::table('food_logs')
            ->select(
                DB::raw('AVG(calories) as avg_calories'),
                DB::raw('AVG(protein) as avg_protein'),
                DB::raw('AVG(carbs) as avg_carbs'),
                DB::raw('AVG(fat) as avg_fat'),
                DB::raw('COUNT(DISTINCT DATE(logged_at)) as days_with_logs')
            )
            ->where('user_id', $userId)
            ->where('logged_at', '>=', $startDate)
            ->first();

        return [
            'avg_calories' => (float) ($stats->avg_calories ?? 0),
            'avg_protein' => (float) ($stats->avg_protein ?? 0),
            'avg_carbs' => (float) ($stats->avg_carbs ?? 0),
            'avg_fat' => (float) ($stats->avg_fat ?? 0),
            'days_with_logs' => (int) ($stats->days_with_logs ?? 0),
            'total_days' => $days,
            'logging_rate' => $stats->days_with_logs > 0 ? ($stats->days_with_logs / $days) * 100 : 0,
        ];
    }

    public function getStatistics(int $userId): array
    {
        $cacheKey = "user_{$userId}_food_statistics";

        return Cache::remember($cacheKey, 3600, function () use ($userId) {
            $totalStats = DB::table('food_logs')
                ->select(
                    DB::raw('COUNT(*) as total_logs'),
                    DB::raw('SUM(calories) as total_calories'),
                    DB::raw('MIN(logged_at) as first_log'),
                    DB::raw('MAX(logged_at) as last_log'),
                    DB::raw('COUNT(DISTINCT DATE(logged_at)) as unique_days')
                )
                ->where('user_id', $userId)
                ->first();

            $mealTypeStats = DB::table('food_logs')
                ->select(
                    'meal_type',
                    DB::raw('COUNT(*) as count'),
                    DB::raw('AVG(calories) as avg_calories')
                )
                ->where('user_id', $userId)
                ->whereNotNull('meal_type')
                ->groupBy('meal_type')
                ->get()
                ->pluck('count', 'meal_type')
                ->toArray();

            $sourceStats = DB::table('food_logs')
                ->select(
                    'source',
                    DB::raw('COUNT(*) as count'),
                    DB::raw('AVG(calories) as avg_calories')
                )
                ->where('user_id', $userId)
                ->groupBy('source')
                ->get()
                ->toArray();

            return [
                'total' => (array) $totalStats,
                'meal_types' => $mealTypeStats,
                'sources' => $sourceStats,
                'consistency' => $this->calculateConsistency($userId),
            ];
        });
    }

    public function hasLoggedToday(int $userId): bool
    {
        return $this->model
            ->where('user_id', $userId)
            ->whereDate('logged_at', today())
            ->exists();
    }

    public function getLastLoggedFood(int $userId): ?FoodLog
    {
        return $this->model
            ->where('user_id', $userId)
            ->orderBy('logged_at', 'desc')
            ->first();
    }

    /**
     * Helper Methods
     */
    private function calculateConsistency(int $userId): array
    {
        $logsByDay = DB::table('food_logs')
            ->select(DB::raw('DATE(logged_at) as log_date'))
            ->where('user_id', $userId)
            ->groupBy(DB::raw('DATE(logged_at)'))
            ->orderBy('log_date')
            ->pluck('log_date')
            ->map(function ($date) {
                return Carbon::parse($date);
            });

        if ($logsByDay->count() < 2) {
            return [
                'streak_days' => $logsByDay->count(),
                'max_streak' => $logsByDay->count(),
                'current_streak' => $logsByDay->count(),
                'consistency_percentage' => 0,
            ];
        }

        // Calculate streaks
        $streaks = [];
        $currentStreak = 1;
        $maxStreak = 1;

        for ($i = 1; $i < $logsByDay->count(); $i++) {
            $diff = $logsByDay[$i]->diffInDays($logsByDay[$i - 1]);

            if ($diff === 1) {
                $currentStreak++;
                $maxStreak = max($maxStreak, $currentStreak);
            } else {
                $streaks[] = $currentStreak;
                $currentStreak = 1;
            }
        }

        $streaks[] = $currentStreak;

        return [
            'total_days_logged' => $logsByDay->count(),
            'streak_days' => $currentStreak,
            'max_streak' => $maxStreak,
            'avg_streak' => count($streaks) > 0 ? array_sum($streaks) / count($streaks) : 0,
            'consistency_percentage' => ($logsByDay->count() / $logsByDay->last()->diffInDays($logsByDay->first()) + 1) * 100,
        ];
    }
}
