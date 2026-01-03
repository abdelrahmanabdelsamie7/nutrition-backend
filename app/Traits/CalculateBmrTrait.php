<?php

namespace App\Traits;

trait CalculateBmrTrait
{
    public function hasCompleteProfile(): bool
    {
        return !empty($this->age) &&
            !empty($this->gender) &&
            !empty($this->height) &&
            !empty($this->weight) &&
            !empty($this->activity_level) &&
            !empty($this->goal);
    }
    public function calculateDailyTargets(): void
    {
        if (!$this->hasCompleteProfile()) {
            return;
        }
        $this->calculateBMR();
        $this->calculateTDEE();
        $this->adjustForGoal();
        $this->calculateMacros();
        $this->save();
    }
    private function calculateBMR(): void
    {
        $weight = (float) $this->weight;
        $height = (float) $this->height;
        $age = (int) $this->age;
        $gender = (string) $this->gender;
        $formula = (string) ($this->bmr_formula ?? 'mifflin');
        $bmr = 0.0;
        switch ($formula) {
            case 'mifflin':
                $bmr = $this->calculateMifflinStJeor($weight, $height, $age, $gender);
                break;
            case 'harris':
                $bmr = $this->calculateHarrisBenedict($weight, $height, $age, $gender);
                break;
            default:
                $bmr = $this->calculateMifflinStJeor($weight, $height, $age, $gender);
        }
        $this->bmr = round($bmr, 2);
    }
    private function calculateMifflinStJeor(float $weight, float $height, int $age, string $gender): float
    {
        if ($gender === 'male') {
            return (10 * $weight) + (6.25 * $height) - (5 * $age) + 5;
        }
        return (10 * $weight) + (6.25 * $height) - (5 * $age) - 161;
    }
    private function calculateHarrisBenedict(float $weight, float $height, int $age, string $gender): float
    {
        if ($gender === 'male') {
            return 88.362 + (13.397 * $weight) + (4.799 * $height) - (5.677 * $age);
        }
        return 447.593 + (9.247 * $weight) + (3.098 * $height) - (4.330 * $age);
    }
    private function calculateTDEE(): void
    {
        $activityMultipliers = [
            'sedentary' => 1.2,
            'light' => 1.375,
            'moderate' => 1.55,
            'active' => 1.725,
            'very_active' => 1.9,
        ];
        $activityLevel = (string) $this->activity_level;
        $multiplier = $activityMultipliers[$activityLevel] ?? 1.55;
        $this->tdee =  round($this->bmr * $multiplier, 2);
    }
    private function adjustForGoal(): void
    {
        $goalAdjustments = [
            'lose_weight' => -500,
            'maintain' => 0,
            'gain_weight' => 300,
            'build_muscle' => 200,
        ];
        $goal = (string) $this->goal;
        $adjustment = $goalAdjustments[$goal] ?? 0;
        $this->daily_calorie_target = max(1200, round($this->tdee + $adjustment));
    }
    private function calculateMacros(): void
    {
        $calories = (float) $this->daily_calorie_target;
        $distributions = [
            'lose_weight' => ['protein' => 0.35, 'fat' => 0.25, 'carbs' => 0.40],
            'build_muscle' => ['protein' => 0.40, 'fat' => 0.25, 'carbs' => 0.35],
            'gain_weight' => ['protein' => 0.30, 'fat' => 0.25, 'carbs' => 0.45],
            'maintain' => ['protein' => 0.30, 'fat' => 0.25, 'carbs' => 0.45],
        ];
        $goal = (string) $this->goal;
        $distribution = $distributions[$goal] ?? $distributions['maintain'];
        $this->daily_protein_target = round(($calories * $distribution['protein']) / 4);
        $this->daily_fat_target = round(($calories * $distribution['fat']) / 9);
        $this->daily_carbs_target = round(($calories * $distribution['carbs']) / 4);
    }
    public function calculateCaloriesFromMacros(float $protein, float $carbs, float $fat): float
    {
        return ($protein * 4) + ($carbs * 4) + ($fat * 9);
    }
    public function getRemainingCalories(): float
    {
        $consumedToday = $this->foodLogs()
            ->whereDate('logged_at', today())
            ->sum('calories');
        return max(0, $this->daily_calorie_target - $consumedToday);
    }
    public function getRemainingMacros(): array
    {
        $todayLogs = $this->foodLogs()
            ->whereDate('logged_at', today())
            ->get(['protein', 'carbs', 'fat']);
        $consumed = [
            'protein' => $todayLogs->sum('protein'),
            'carbs' => $todayLogs->sum('carbs'),
            'fat' => $todayLogs->sum('fat'),
        ];
        return [
            'protein' => max(0, $this->daily_protein_target - $consumed['protein']),
            'carbs' => max(0, $this->daily_carbs_target - $consumed['carbs']),
            'fat' => max(0, $this->daily_fat_target - $consumed['fat']),
        ];
    }
}
