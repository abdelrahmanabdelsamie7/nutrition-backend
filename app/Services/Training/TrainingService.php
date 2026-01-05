<?php

namespace App\Services\Training;

use App\Interfaces\Services\TrainingServiceInterface;
use App\Interfaces\Repositories\TrainingLogRepositoryInterface;
use App\Interfaces\Repositories\UserRepositoryInterface;
use Illuminate\Support\Facades\Log;

class TrainingService implements TrainingServiceInterface
{
    public function __construct(
        private TrainingLogRepositoryInterface $trainingRepository,
        private UserRepositoryInterface $userRepository
    ) {}

    /**
     * Calculate calories burned for an activity
     */
    public function calculateCaloriesBurned(
        string $activity,
        int $duration,
        float $weight,
        float $intensity = 5.0
    ): float {
        $metValue = $this->getActivityMET($activity);

        // Adjust MET based on intensity (1-10 scale)
        $intensityMultiplier = 1 + (($intensity - 5) * 0.1);
        $adjustedMET = $metValue * $intensityMultiplier;

        // Calories = MET * weight(kg) * time(hours)
        $hours = $duration / 60;
        $calories = $adjustedMET * $weight * $hours;

        return round(max($calories, 0), 1);
    }

    /**
     * Log training session
     */
    public function logTrainingSession(string $userId, array $data): array
    {
        $user = $this->userRepository->find($userId);

        // Calculate calories burned
        $caloriesBurned = $this->calculateCaloriesBurned(
            $data['activity_name'],
            $data['duration'],
            $user->weight,
            $data['intensity_level'] ?? 5.0
        );

        $trainingData = [
            'user_id' => $userId,
            'activity_name' => $data['activity_name'],
            'activity_type' => $data['activity_type'] ?? $this->classifyActivity($data['activity_name']),
            'duration' => $data['duration'],
            'intensity_level' => $data['intensity_level'] ?? 5.0,
            'estimated_calories_burned' => $caloriesBurned,
            'calorie_calculation_meta' => [
                'method' => 'MET',
                'weight_used' => $user->weight,
                'intensity' => $data['intensity_level'] ?? 5.0,
                'calculated_at' => now()->toISOString()
            ],
            'reps' => $data['reps'] ?? null,
            'sets' => $data['sets'] ?? null,
            'weight_used' => $data['weight_used'] ?? null,
            'distance' => $data['distance'] ?? null,
            'heart_rate_avg' => $data['heart_rate_avg'] ?? null,
            'notes' => $data['notes'] ?? null,
            'performed_at' => $data['performed_at'] ?? now(),
        ];

        $log = $this->trainingRepository->create($trainingData);

        Log::info('Training session logged', [
            'user_id' => $userId,
            'activity' => $data['activity_name'],
            'calories_burned' => $caloriesBurned,
            'duration' => $data['duration']
        ]);

        return [
            'log' => $log,
            'calories_burned' => $caloriesBurned,
            'summary' => $this->generateSessionSummary($log)
        ];
    }

    /**
     * Get training recommendations
     */
    public function getTrainingRecommendations(string $userId, array $goals): array
    {
        $user = $this->userRepository->find($userId);
        $trainingHistory = $this->trainingRepository->getUserLogs($userId);

        $recommendations = [];

        // Based on user goal
        switch ($user->goal) {
            case 'lose_weight':
                $recommendations = $this->getWeightLossRecommendations($user, $trainingHistory);
                break;
            case 'build_muscle':
                $recommendations = $this->getMuscleBuildingRecommendations($user, $trainingHistory);
                break;
            case 'gain_weight':
                $recommendations = $this->getWeightGainRecommendations($user, $trainingHistory);
                break;
            default:
                $recommendations = $this->getMaintenanceRecommendations($user, $trainingHistory);
        }

        // Adjust based on activity level
        $recommendations = $this->adjustForActivityLevel($recommendations, $user->activity_level);

        // Add consistency tips
        $recommendations['consistency_tips'] = $this->getConsistencyTips($trainingHistory);

        return $recommendations;
    }

    /**
     * Calculate training volume
     */
    public function calculateTrainingVolume(array $sessions): array
    {
        $volume = [
            'total_sessions' => count($sessions),
            'total_duration' => 0,
            'total_calories' => 0,
            'weekly_average' => [],
            'intensity_distribution' => []
        ];

        $weeklyData = [];
        $intensityCounts = [
            'light' => 0,
            'moderate' => 0,
            'vigorous' => 0,
            'maximum' => 0
        ];

        foreach ($sessions as $session) {
            $volume['total_duration'] += $session['duration'];
            $volume['total_calories'] += $session['estimated_calories_burned'];

            // Weekly grouping
            $week = date('W', strtotime($session['performed_at']));
            if (!isset($weeklyData[$week])) {
                $weeklyData[$week] = [
                    'sessions' => 0,
                    'duration' => 0,
                    'calories' => 0
                ];
            }
            $weeklyData[$week]['sessions']++;
            $weeklyData[$week]['duration'] += $session['duration'];
            $weeklyData[$week]['calories'] += $session['estimated_calories_burned'];

            // Intensity classification
            $intensity = $session['intensity_level'] ?? 5;
            if ($intensity <= 3) $intensityCounts['light']++;
            elseif ($intensity <= 6) $intensityCounts['moderate']++;
            elseif ($intensity <= 8) $intensityCounts['vigorous']++;
            else $intensityCounts['maximum']++;
        }

        // Calculate weekly averages
        if (!empty($weeklyData)) {
            $totalWeeks = count($weeklyData);
            $volume['weekly_average'] = [
                'sessions' => $volume['total_sessions'] / $totalWeeks,
                'duration' => $volume['total_duration'] / $totalWeeks,
                'calories' => $volume['total_calories'] / $totalWeeks
            ];
        }

        // Calculate intensity distribution
        $totalSessions = $volume['total_sessions'];
        if ($totalSessions > 0) {
            foreach ($intensityCounts as $key => $count) {
                $volume['intensity_distribution'][$key] = [
                    'count' => $count,
                    'percentage' => ($count / $totalSessions) * 100
                ];
            }
        }

        return $volume;
    }

    /**
     * Get activity MET values
     */
    public function getActivityMET(string $activity): float
    {
        $metValues = [
            // Cardio
            'running' => 9.8,
            'jogging' => 7.0,
            'walking' => 3.5,
            'cycling' => 8.0,
            'swimming' => 8.0,
            'rowing' => 7.0,
            'jump rope' => 10.0,

            // Strength
            'weight lifting' => 6.0,
            'bodyweight exercises' => 5.0,
            'calisthenics' => 5.5,
            'circuit training' => 8.0,

            // Sports
            'basketball' => 8.0,
            'soccer' => 8.0,
            'tennis' => 8.0,
            'volleyball' => 4.0,

            // Other
            'yoga' => 3.0,
            'pilates' => 3.5,
            'stretching' => 2.5,
            'dancing' => 5.0,
            'hiking' => 6.0,
        ];

        $activityLower = strtolower($activity);

        foreach ($metValues as $key => $value) {
            if (strpos($activityLower, $key) !== false) {
                return $value;
            }
        }

        // Default for unknown activities
        return 5.0;
    }

    /**
     * Suggest training plan
     */
    public function suggestTrainingPlan(string $userId, string $goal, int $daysPerWeek): array
    {
        $plans = [
            'lose_weight' => [
                3 => $this->get3DayWeightLossPlan(),
                4 => $this->get4DayWeightLossPlan(),
                5 => $this->get5DayWeightLossPlan(),
                6 => $this->get6DayWeightLossPlan(),
            ],
            'build_muscle' => [
                3 => $this->get3DayMuscleBuildingPlan(),
                4 => $this->get4DayMuscleBuildingPlan(),
                5 => $this->get5DayMuscleBuildingPlan(),
                6 => $this->get6DayMuscleBuildingPlan(),
            ],
            'maintain' => [
                3 => $this->get3DayMaintenancePlan(),
                4 => $this->get4DayMaintenancePlan(),
                5 => $this->get5DayMaintenancePlan(),
            ]
        ];

        $plan = $plans[$goal][$daysPerWeek] ?? $plans['maintain'][3];

        // Personalize based on user history
        $plan = $this->personalizePlan($plan, $userId);

        return $plan;
    }

    /**
     * Helper Methods
     */
    private function classifyActivity(string $activityName): string
    {
        $activityLower = strtolower($activityName);

        $categories = [
            'cardio' => ['run', 'jog', 'walk', 'cycle', 'swim', 'row', 'cardio', 'aerob'],
            'strength' => ['lift', 'weight', 'strength', 'gym', 'bench', 'squat', 'deadlift'],
            'flexibility' => ['yoga', 'stretch', 'pilates', 'flexibility', 'mobility'],
            'sports' => ['basketball', 'soccer', 'tennis', 'football', 'sport'],
            'hiit' => ['hiit', 'interval', 'tabata', 'circuit'],
        ];

        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($activityLower, $keyword) !== false) {
                    return $category;
                }
            }
        }

        return 'other';
    }

    private function generateSessionSummary($log): array
    {
        return [
            'activity' => $log->activity_name,
            'duration_minutes' => $log->duration,
            'calories_burned' => $log->estimated_calories_burned,
            'calories_per_minute' => $log->duration > 0 ? round($log->estimated_calories_burned / $log->duration, 2) : 0,
            'intensity' => $log->getIntensityDescription(),
            'met_value' => $this->getActivityMET($log->activity_name)
        ];
    }

    private function getWeightLossRecommendations($user, $trainingHistory): array
    {
        $recommendations = [
            'frequency' => '5-6 days per week',
            'split' => 'Alternate cardio and full-body strength',
            'cardio' => [
                'type' => 'Mix of steady state and HIIT',
                'duration' => '30-60 minutes per session',
                'weekly_total' => '150-300 minutes'
            ],
            'strength' => [
                'focus' => 'Full-body compound movements',
                'frequency' => '2-3 times per week',
                'volume' => '2-3 sets of 8-12 reps'
            ],
            'progression' => 'Increase duration or intensity by 10% weekly'
        ];

        return $recommendations;
    }

    private function getMuscleBuildingRecommendations($user, $trainingHistory): array
    {
        $recommendations = [
            'frequency' => '4-5 days per week',
            'split' => 'Push/Pull/Legs or Upper/Lower split',
            'volume' => '10-20 sets per muscle group weekly',
            'intensity' => 'Moderate to high (RPE 7-9)',
            'progressive_overload' => [
                'method' => 'Increase weight, reps, or sets weekly',
                'target' => '2.5-5% increase in load monthly'
            ],
            'recovery' => '48-72 hours between training same muscle group'
        ];

        return $recommendations;
    }

    private function adjustForActivityLevel(array $recommendations, string $activityLevel): array
    {
        $adjustments = [
            'sedentary' => ['reduce_by' => 0.7, 'note' => 'Start slow and build gradually'],
            'light' => ['reduce_by' => 0.85, 'note' => 'Moderate intensity recommended'],
            'moderate' => ['reduce_by' => 1.0, 'note' => 'Standard recommendations'],
            'active' => ['increase_by' => 1.15, 'note' => 'Can handle higher volume'],
            'very_active' => ['increase_by' => 1.3, 'note' => 'Advanced programming suitable']
        ];

        $adjustment = $adjustments[$activityLevel] ?? $adjustments['moderate'];

        $recommendations['activity_level_adjustment'] = $adjustment['note'];

        return $recommendations;
    }

    private function getConsistencyTips($trainingHistory): array
    {
        $tips = [
            'Schedule workouts in your calendar',
            'Prepare gym clothes the night before',
            'Track your progress weekly',
            'Find an accountability partner',
            'Focus on consistency over perfection'
        ];

        // Add personalized tips based on history
        if (count($trainingHistory) < 5) {
            array_unshift($tips, 'Start with 2-3 sessions per week and build gradually');
        }

        return $tips;
    }

    private function personalizePlan(array $plan, string $userId): array
    {
        $user = $this->userRepository->find($userId);

        // Adjust based on user profile
        if ($user->gender === 'female') {
            $plan['notes'][] = 'Women may benefit from slightly higher rep ranges (12-15)';
        }

        if ($user->age > 40) {
            $plan['notes'][] = 'Include proper warm-up and cool-down. Focus on mobility.';
        }

        return $plan;
    }

    // Training plan templates
    private function get3DayWeightLossPlan(): array
    {
        return [
            'monday' => ['focus' => 'Full Body Strength + Cardio', 'exercises' => ['Squats', 'Push-ups', 'Rows', '30min Cardio']],
            'wednesday' => ['focus' => 'HIIT Cardio', 'exercises' => ['Interval Training', 'Bodyweight Circuits']],
            'friday' => ['focus' => 'Full Body Strength + Cardio', 'exercises' => ['Deadlifts', 'Overhead Press', 'Pull-ups', '30min Cardio']],
            'weekend' => ['focus' => 'Active Recovery', 'exercises' => ['Walking', 'Stretching']]
        ];
    }

    private function get4DayMuscleBuildingPlan(): array
    {
        return [
            'monday' => ['focus' => 'Chest & Triceps', 'exercises' => ['Bench Press', 'Incline Press', 'Chest Flyes', 'Tricep Extensions']],
            'tuesday' => ['focus' => 'Back & Biceps', 'exercises' => ['Pull-ups', 'Rows', 'Lat Pulldowns', 'Bicep Curls']],
            'thursday' => ['focus' => 'Legs', 'exercises' => ['Squats', 'Lunges', 'Leg Press', 'Calf Raises']],
            'friday' => ['focus' => 'Shoulders & Arms', 'exercises' => ['Overhead Press', 'Lateral Raises', 'Arm Supersets']]
        ];
    }

    private function get3DayMaintenancePlan(): array
    {
        return [
            'monday' => ['focus' => 'Full Body A', 'exercises' => ['Squats', 'Bench Press', 'Rows', 'Planks']],
            'wednesday' => ['focus' => 'Cardio & Core', 'exercises' => ['30min Cardio', 'Core Circuit']],
            'friday' => ['focus' => 'Full Body B', 'exercises' => ['Deadlifts', 'Overhead Press', 'Pull-ups', 'Lunges']]
        ];
    }
}
