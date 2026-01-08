<?php

namespace App\Services\Training;

use App\Traits\TrainingPlanTemplatesTrait;
use App\Interfaces\Services\TrainingServiceInterface;
use App\Interfaces\Repositories\UserRepositoryInterface;
use App\Traits\Translations\TranslationTraingingPlansTrait;
use App\Interfaces\Repositories\TrainingLogRepositoryInterface;

class TrainingService implements TrainingServiceInterface
{
    use TrainingPlanTemplatesTrait, TranslationTraingingPlansTrait;
    public function __construct(
        private TrainingLogRepositoryInterface $trainingRepository,
        private UserRepositoryInterface $userRepository
    ) {}

    public function logTrainingSession(string $userId, array $data): array
    {
        $user = $this->userRepository->find($userId);

        $originalActivity = $data['activity_name'];
        $translatedActivity = $this->translateActivityToEnglish($originalActivity);

        $userWeight = $this->getUserWeightWithFallback($user);

        $caloriesBurned = $this->calculateCaloriesBurned(
            $translatedActivity,
            $data['duration'],
            $userWeight,
            $data['intensity_level'] ?? 5.0
        );

        $trainingData = [
            'user_id' => $userId,
            'activity_name' => $originalActivity,
            'activity_name_en' => $translatedActivity,
            'activity_type' => $data['activity_type'] ?? $this->classifyActivity($translatedActivity),
            'duration' => $data['duration'],
            'intensity_level' => $data['intensity_level'] ?? 5.0,
            'estimated_calories_burned' => $caloriesBurned,
            'calorie_calculation_meta' => [
                'method' => 'MET',
                'weight_used' => $userWeight,
                'intensity' => $data['intensity_level'] ?? 5.0,
                'calculated_at' => now()->toISOString(),
                'original_activity' => $originalActivity,
                'translated_activity' => $translatedActivity,
                'weight_source' => $user->weight ? 'user_profile' : 'default_fallback'
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

        return [
            'log' => $log,
            'calories_burned' => $caloriesBurned,
            'summary' => $this->generateSessionSummary($log)
        ];
    }

    private function getUserWeightWithFallback($user): float
    {
        if (isset($user->weight) && is_numeric($user->weight) && $user->weight > 0) {
            return (float) $user->weight;
        }

        $defaultWeight = 70.0;

        if (isset($user->gender) && strtolower($user->gender) === 'female') {
            $defaultWeight = 60.0;
        }

        return $defaultWeight;
    }

    public function calculateCaloriesBurned(
        string $activity,
        int $duration,
        ?float $weight = null,
        float $intensity = 5.0
    ): float {
        $activity = $this->translateActivityToEnglish($activity);

        $metValue = $this->getActivityMET($activity);

        $intensityMultiplier = 1 + (($intensity - 5) * 0.1);
        $adjustedMET = $metValue * $intensityMultiplier;

        $safeWeight = $weight ?? 70.0;

        $hours = $duration / 60;
        $calories = $adjustedMET * $safeWeight * $hours;

        return round(max($calories, 0), 1);
    }

    public function getTrainingRecommendations(string $userId, array $goals): array
    {
        $user = $this->userRepository->find($userId);
        $trainingHistory = $this->trainingRepository->getUserLogs($userId);
        $recommendations = [];

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
            case 'maintain':
            default:
                $recommendations = $this->getMaintenanceRecommendations($user, $trainingHistory);
        }

        $recommendations = $this->adjustForActivityLevel($recommendations, $user->activity_level);

        $recommendations['consistency_tips'] = $this->getConsistencyTips($trainingHistory);

        if ($user->language === 'ar' || $user->language === 'arabic') {
            $recommendations = $this->translateRecommendationsToArabic($recommendations);
        }

        return $recommendations;
    }

    private function getWeightGainRecommendations($user, $trainingHistory): array
    {
        $recommendations = [
            'goal' => 'زيادة الوزن والعضلات',
            'frequency' => '4-5 days per week',
            'split' => 'Focus on strength training with minimal cardio',
            'strength_training' => [
                'focus' => 'Compound movements with progressive overload',
                'frequency' => '3-4 times per week',
                'volume' => '3-4 sets of 6-8 reps',
                'intensity' => 'High intensity (RPE 8-9)',
                'rest_periods' => '2-3 minutes between sets'
            ],
            'cardio' => [
                'type' => 'Minimal low-intensity cardio',
                'duration' => '15-20 minutes per session, 1-2 times per week',
                'purpose' => 'Maintain cardiovascular health without excessive calorie burn'
            ],
            'nutrition_focus' => [
                'calorie_surplus' => '300-500 calories above maintenance',
                'protein_intake' => '1.6-2.2g per kg of body weight',
                'carbohydrates' => 'High carb intake for energy and recovery',
                'meal_frequency' => '5-6 meals per day'
            ],
            'progression' => 'Increase weight by 2.5-5% when you can complete all reps with good form',
            'recovery' => '48-72 hours between training same muscle groups, prioritize sleep (7-9 hours)',
            'supplementation' => [
                'recommended' => ['Protein powder', 'Creatine monohydrate', 'Mass gainers if needed'],
                'optional' => ['BCAAs', 'Beta-alanine']
            ]
        ];

        // Personalize based on user data
        if ($user->gender === 'female') {
            $recommendations['notes'][] = 'Women may need slightly lower calorie surplus (200-300 calories)';
        }

        if ($user->activity_level === 'sedentary') {
            $recommendations['cardio']['frequency'] = '1 time per week';
        }

        return $recommendations;
    }

    private function getMaintenanceRecommendations($user, $trainingHistory): array
    {
        $recommendations = [
            'goal' => 'الحفاظ على اللياقة والوزن الحالي',
            'frequency' => '3-4 days per week',
            'split' => 'Balanced full-body workouts',
            'workout_structure' => [
                'strength' => '2 times per week',
                'cardio' => '1-2 times per week',
                'flexibility' => '1 time per week'
            ],
            'strength_training' => [
                'focus' => 'Maintain strength and muscle mass',
                'volume' => '2-3 sets of 8-12 reps',
                'intensity' => 'Moderate intensity (RPE 6-8)',
                'exercises' => 'Focus on compound movements with accessory work'
            ],
            'cardio' => [
                'type' => 'Mix of steady state and intervals',
                'duration' => '30-45 minutes per session',
                'weekly_total' => '75-150 minutes'
            ],
            'flexibility_mobility' => [
                'frequency' => '1-2 times per week',
                'activities' => ['Yoga', 'Stretching', 'Foam rolling'],
                'duration' => '20-30 minutes per session'
            ],
            'nutrition_focus' => [
                'calories' => 'Maintenance level',
                'protein_intake' => '1.2-1.6g per kg of body weight',
                'macronutrient_balance' => 'Balanced diet with whole foods'
            ],
            'progression' => 'Maintain current strength levels, focus on technique and consistency',
            'recovery' => '48 hours between strength sessions, active recovery on off days',
            'periodization' => 'Change exercises every 4-6 weeks to prevent plateaus'
        ];

        if (count($trainingHistory) > 10) {
            $recommendations['advanced_options'] = [
                'deload_weeks' => 'Take a deload week every 8-12 weeks',
                'skill_work' => 'Incorporate skill-based training or new activities'
            ];
        }

        return $recommendations;
    }

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

            $intensity = $session['intensity_level'] ?? 5;
            if ($intensity <= 3) $intensityCounts['light']++;
            elseif ($intensity <= 6) $intensityCounts['moderate']++;
            elseif ($intensity <= 8) $intensityCounts['vigorous']++;
            else $intensityCounts['maximum']++;
        }

        if (!empty($weeklyData)) {
            $totalWeeks = count($weeklyData);
            $volume['weekly_average'] = [
                'sessions' => $volume['total_sessions'] / $totalWeeks,
                'duration' => $volume['total_duration'] / $totalWeeks,
                'calories' => $volume['total_calories'] / $totalWeeks
            ];
        }

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

    public function getActivityMET(string $activity): float
    {
        $activity = $this->translateActivityToEnglish($activity);

        $metValues = [
            'running' => 9.8,
            'jogging' => 7.0,
            'walking' => 3.5,
            'cycling' => 8.0,
            'swimming' => 8.0,
            'rowing' => 7.0,
            'jump rope' => 10.0,
            'brisk walking' => 5.0,

            'weight lifting' => 6.0,
            'bodyweight exercises' => 5.0,
            'calisthenics' => 5.5,
            'circuit training' => 8.0,
            'push ups' => 4.0,
            'pull ups' => 4.5,
            'squats' => 5.0,

            'basketball' => 8.0,
            'soccer' => 8.0,
            'tennis' => 8.0,
            'volleyball' => 4.0,

            'yoga' => 3.0,
            'pilates' => 3.5,
            'stretching' => 2.5,
            'dancing' => 5.0,
            'hiking' => 6.0,
            'gym' => 5.0,
            'exercise' => 5.0,
        ];

        $activityLower = strtolower($activity);

        foreach ($metValues as $key => $value) {
            if (str_contains($activityLower, $key)) {
                return $value;
            }
        }

        return 5.0;
    }

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
                // 3 => $this->get3DayMuscleBuildingPlan(),
                4 => $this->get4DayMuscleBuildingPlan(),
                // 5 => $this->get5DayMuscleBuildingPlan(),
                6 => $this->get6DayMuscleBuildingPlan(),
            ],
            'gain_weight' => [
                3 => $this->get3DayWeightGainPlan(),
                4 => $this->get4DayWeightGainPlan(),
                5 => $this->get5DayWeightGainPlan(),
            ],
            'maintain' => [
                3 => $this->get3DayMaintenancePlan(),
                4 => $this->get4DayMaintenancePlan(),
                5 => $this->get5DayMaintenancePlan(),
            ]
        ];

        $plan = $plans[$goal][$daysPerWeek] ?? $plans['maintain'][3];

        $plan = $this->personalizePlan($plan, $userId);

        return $plan;
    }

    public function suggestTrainingPlanArabic(string $userId, string $goal, int $daysPerWeek): array
    {
        $englishPlan = $this->suggestTrainingPlan($userId, $goal, $daysPerWeek);

        return $this->translatePlanToArabic($englishPlan);
    }

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

        if (count($trainingHistory) < 5) {
            array_unshift($tips, 'Start with 2-3 sessions per week and build gradually');
        }

        return $tips;
    }

    private function personalizePlan(array $plan, string $userId): array
    {
        $user = $this->userRepository->find($userId);

        if ($user->gender === 'female') {
            $plan['notes'][] = 'Women may benefit from slightly higher rep ranges (12-15)';
        }

        if ($user->age > 40) {
            $plan['notes'][] = 'Include proper warm-up and cool-down. Focus on mobility.';
        }

        return $plan;
    }
}
