<?php

namespace App\Repositories\Eloquent;

use App\Interfaces\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    public function updateProfile(string $userId, array $data): User
    {
        $user = $this->findOrFail($userId);
        $user->fill($data);
        if ($user->isDirty(['age', 'gender', 'height', 'weight', 'activity_level', 'goal'])) {
            $user->calculateDailyTargets();
        }
        $user->save();
        Cache::forget("user_{$userId}_stats");
        Cache::forget("user_{$userId}_daily_progress");
        return $user;
    }

    public function getUserStats(string $userId): array
    {
        $cacheKey = "user_{$userId}_stats";
        return Cache::remember($cacheKey, 3600, function () use ($userId) {
            $user = $this->findOrFail($userId);
            $foodStats = DB::table('food_logs')
                ->select(
                    DB::raw('COUNT(*) as total_logs'),
                    DB::raw('SUM(calories) as total_calories'),
                    DB::raw('AVG(calories) as avg_daily_calories'),
                    DB::raw('MAX(logged_at) as last_log_date')
                )
                ->where('user_id', $userId)
                ->first();
            $trainingStats = DB::table('training_logs')
                ->select(
                    DB::raw('COUNT(*) as total_sessions'),
                    DB::raw('SUM(duration) as total_minutes'),
                    DB::raw('SUM(estimated_calories_burned) as total_calories_burned'),
                    DB::raw('MAX(performed_at) as last_session_date')
                )
                ->where('user_id', $userId)
                ->first();
            $aiStats = DB::table('ai_recommendations')
                ->select(
                    DB::raw('COUNT(*) as total_recommendations'),
                    DB::raw('AVG(adherence_score) as avg_adherence_score'),
                    DB::raw('SUM(CASE WHEN is_helpful = true THEN 1 ELSE 0 END) as helpful_count')
                )
                ->where('user_id', $userId)
                ->first();
            return [
                'food' => (array) $foodStats,
                'training' => (array) $trainingStats,
                'ai' => (array) $aiStats,
                'profile_completion' => $user->hasCompleteProfile(),
                'days_active' => $this->calculateActiveDays($userId),
            ];
        });
    }

    public function getUsersWithNutritionSummary(array $filters = [], int $perPage = 20): array
    {
        $query = $this->model->query();
        if (!empty($filters['activity_level'])) {
            $query->where('activity_level', $filters['activity_level']);
        }
        if (!empty($filters['goal'])) {
            $query->where('goal', $filters['goal']);
        }
        if (!empty($filters['diet_type'])) {
            $query->where('diet_type', $filters['diet_type']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('email', 'like', "%{$filters['search']}%");
            });
        }
        $query->withCount(['foodLogs as total_food_logs'])
            ->withSum(['foodLogs as total_calories_consumed' => function ($q) {
                $q->whereDate('logged_at', '>=', now()->subDays(7));
            }], 'calories')
            ->withSum(['trainingLogs as total_calories_burned' => function ($q) {
                $q->whereDate('performed_at', '>=', now()->subDays(7));
            }], 'estimated_calories_burned');
        $query->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc');
        return $query->paginate($perPage)->toArray();
    }

    public function getDailyProgress(string $userId, Carbon $date): array
    {
        $cacheKey = "user_{$userId}_daily_progress_{$date->format('Y-m-d')}";
        return Cache::remember($cacheKey, 300, function () use ($userId, $date) {
            $user = $this->findOrFail($userId);
            $foodSummary = DB::table('food_logs')
                ->select(
                    DB::raw('SUM(calories) as calories'),
                    DB::raw('SUM(protein) as protein'),
                    DB::raw('SUM(carbs) as carbs'),
                    DB::raw('SUM(fat) as fat'),
                    DB::raw('COUNT(*) as meal_count')
                )
                ->where('user_id', $userId)
                ->whereDate('logged_at', $date)
                ->first();
            $trainingSummary = DB::table('training_logs')
                ->select(
                    DB::raw('SUM(estimated_calories_burned) as calories_burned'),
                    DB::raw('SUM(duration) as total_minutes'),
                    DB::raw('COUNT(*) as session_count')
                )
                ->where('user_id', $userId)
                ->whereDate('performed_at', $date)
                ->first();
            $caloriesConsumed = $foodSummary->calories ?? 0;
            $caloriesBurned = $trainingSummary->calories_burned ?? 0;
            return [
                'date' => $date->format('Y-m-d'),
                'food' => [
                    'calories' => (float) $caloriesConsumed,
                    'protein' => (float) ($foodSummary->protein ?? 0),
                    'carbs' => (float) ($foodSummary->carbs ?? 0),
                    'fat' => (float) ($foodSummary->fat ?? 0),
                    'meal_count' => (int) ($foodSummary->meal_count ?? 0),
                ],
                'training' => [
                    'calories_burned' => (float) $caloriesBurned,
                    'total_minutes' => (int) ($trainingSummary->total_minutes ?? 0),
                    'session_count' => (int) ($trainingSummary->session_count ?? 0),
                ],
                'targets' => [
                    'calories' => $user->daily_calorie_target,
                    'protein' => $user->daily_protein_target,
                    'carbs' => $user->daily_carbs_target,
                    'fat' => $user->daily_fat_target,
                ],
                'net_calories' => $caloriesConsumed - $caloriesBurned,
                'calorie_surplus_deficit' => $caloriesConsumed - $user->daily_calorie_target,
                'progress_percentage' => $this->calculateProgressPercentage($user, $caloriesConsumed),
            ];
        });
    }

    public function getWeeklySummary(string $userId, Carbon $startDate, Carbon $endDate): array
    {
        $user = $this->findOrFail($userId);

        $dailyProgress = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dailyProgress[$currentDate->format('Y-m-d')] = $this->getDailyProgress($userId, $currentDate);
            $currentDate->addDay();
        }

        $totalCalories = array_sum(array_column(array_column($dailyProgress, 'food'), 'calories'));
        $totalProtein = array_sum(array_column(array_column($dailyProgress, 'food'), 'protein'));
        $totalCarbs = array_sum(array_column(array_column($dailyProgress, 'food'), 'carbs'));
        $totalFat = array_sum(array_column(array_column($dailyProgress, 'food'), 'fat'));
        $totalBurned = array_sum(array_column(array_column($dailyProgress, 'training'), 'calories_burned'));

        $weeklyTargetCalories = $user->daily_calorie_target * 7;

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'totals' => [
                'calories_consumed' => $totalCalories,
                'calories_burned' => $totalBurned,
                'protein' => $totalProtein,
                'carbs' => $totalCarbs,
                'fat' => $totalFat,
                'meal_count' => array_sum(array_column(array_column($dailyProgress, 'food'), 'meal_count')),
                'training_sessions' => array_sum(array_column(array_column($dailyProgress, 'training'), 'session_count')),
            ],
            'averages' => [
                'daily_calories' => $totalCalories / 7,
                'daily_protein' => $totalProtein / 7,
                'daily_carbs' => $totalCarbs / 7,
                'daily_fat' => $totalFat / 7,
            ],
            'targets' => [
                'weekly_calories' => $weeklyTargetCalories,
                'daily_average_target' => $user->daily_calorie_target,
            ],
            'performance' => [
                'calorie_difference' => $totalCalories - $weeklyTargetCalories,
                'achievement_rate' => $this->calculateAchievementRate($dailyProgress, $user),
                'consistency_score' => $this->calculateConsistencyScore($dailyProgress),
            ],
            'daily_breakdown' => $dailyProgress,
        ];
    }

    public function getRemainingCalories(string $userId): float
    {
        $user = $this->findOrFail($userId);
        return $user->getRemainingCalories();
    }

    public function getMostConsumedFoods(string $userId, int $limit = 5): array
    {
        return DB::table('food_logs')
            ->select(
                DB::raw('LOWER(raw_input) as food_item'),
                DB::raw('COUNT(*) as log_count'),
                DB::raw('AVG(calories) as avg_calories'),
                DB::raw('SUM(calories) as total_calories')
            )
            ->where('user_id', $userId)
            ->groupBy(DB::raw('LOWER(raw_input)'))
            ->orderByDesc('log_count')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'food_item' => $item->food_item,
                    'log_count' => $item->log_count,
                    'avg_calories' => (float) $item->avg_calories,
                    'total_calories' => (float) $item->total_calories,
                ];
            })
            ->toArray();
    }

    public function getUsersByActivityLevel(string $activityLevel): Collection
    {
        return $this->model->where('activity_level', $activityLevel)->get();
    }

    public function getInactiveUsers(int $days = 7): Collection
    {
        $cutoffDate = now()->subDays($days);

        return $this->model->whereDoesntHave('foodLogs', function ($query) use ($cutoffDate) {
            $query->where('logged_at', '>=', $cutoffDate);
        })->whereDoesntHave('trainingLogs', function ($query) use ($cutoffDate) {
            $query->where('performed_at', '>=', $cutoffDate);
        })->get();
    }

    public function updateDailyTargets(string $userId, array $targets): bool
    {
        $user = $this->findOrFail($userId);

        $user->update([
            'daily_calorie_target' => $targets['calories'] ?? $user->daily_calorie_target,
            'daily_protein_target' => $targets['protein'] ?? $user->daily_protein_target,
            'daily_carbs_target' => $targets['carbs'] ?? $user->daily_carbs_target,
            'daily_fat_target' => $targets['fat'] ?? $user->daily_fat_target,
        ]);

        return true;
    }

    public function getUsersNeedingRecommendations(): Collection
    {
        // Get users who haven't received a recommendation in the last 3 days
        $cutoffDate = now()->subDays(3);

        return $this->model->where('ai_recommendations_enabled', true)
            ->whereDoesntHave('aiRecommendations', function ($query) use ($cutoffDate) {
                $query->where('created_at', '>=', $cutoffDate);
            })
            ->whereHas('foodLogs', function ($query) {
                $query->whereDate('logged_at', '>=', now()->subDays(7));
            })
            ->get();
    }

    /**
     * Helper Methods
     */
    private function calculateActiveDays(string $userId): int
    {
        $firstLog = DB::table('food_logs')
            ->where('user_id', $userId)
            ->orderBy('logged_at')
            ->value('logged_at');

        if (!$firstLog) {
            return 0;
        }

        $daysWithLogs = DB::table('food_logs')
            ->select(DB::raw('DATE(logged_at) as log_date'))
            ->where('user_id', $userId)
            ->groupBy(DB::raw('DATE(logged_at)'))
            ->count();

        return $daysWithLogs;
    }

    private function calculateProgressPercentage(User $user, float $caloriesConsumed): float
    {
        if ($user->daily_calorie_target <= 0) {
            return 0;
        }

        $percentage = ($caloriesConsumed / $user->daily_calorie_target) * 100;

        // Cap at 150% for visualization
        return min($percentage, 150);
    }

    private function calculateAchievementRate(array $dailyProgress, User $user): float
    {
        $daysOnTarget = 0;
        $totalDays = count($dailyProgress);

        foreach ($dailyProgress as $progress) {
            $calories = $progress['food']['calories'] ?? 0;
            $target = $user->daily_calorie_target;

            // Consider within ±10% as on target
            $lowerBound = $target * 0.9;
            $upperBound = $target * 1.1;

            if ($calories >= $lowerBound && $calories <= $upperBound) {
                $daysOnTarget++;
            }
        }

        return $totalDays > 0 ? ($daysOnTarget / $totalDays) * 100 : 0;
    }

    private function calculateConsistencyScore(array $dailyProgress): float
    {
        $calories = array_column(array_column($dailyProgress, 'food'), 'calories');

        if (count($calories) < 2) {
            return 0;
        }

        $mean = array_sum($calories) / count($calories);
        $variance = 0;

        foreach ($calories as $value) {
            $variance += pow($value - $mean, 2);
        }

        $variance /= count($calories);
        $stdDev = sqrt($variance);

        // Lower standard deviation = more consistent
        $consistency = 100 - min(($stdDev / $mean) * 100, 100);

        return max(0, $consistency);
    }
}
