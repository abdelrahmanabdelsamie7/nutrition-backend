<?php


namespace App\Traits;

trait TrainingPlanTemplatesTrait{
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

    private function get3DayWeightGainPlan(): array
    {
        return [
            'monday' => ['focus' => 'Full Body Strength A', 'exercises' => ['Squats', 'Bench Press', 'Bent Over Rows', 'Leg Curls']],
            'wednesday' => ['focus' => 'Full Body Strength B', 'exercises' => ['Deadlifts', 'Overhead Press', 'Pull-ups', 'Leg Press']],
            'friday' => ['focus' => 'Accessory & Volume', 'exercises' => ['Incline Press', 'Lat Pulldowns', 'Arm Work', 'Calf Raises']],
            'weekend' => ['focus' => 'Light Activity', 'exercises' => ['Walking', 'Mobility Work']]
        ];
    }

    private function get4DayWeightGainPlan(): array
    {
        return [
            'monday' => ['focus' => 'Upper Body Strength', 'exercises' => ['Bench Press', 'Rows', 'Overhead Press', 'Bicep Curls']],
            'tuesday' => ['focus' => 'Lower Body Strength', 'exercises' => ['Squats', 'Romanian Deadlifts', 'Leg Press', 'Calf Raises']],
            'thursday' => ['focus' => 'Upper Body Hypertrophy', 'exercises' => ['Incline Press', 'Lat Pulldowns', 'Shoulder Press', 'Tricep Extensions']],
            'friday' => ['focus' => 'Lower Body Hypertrophy', 'exercises' => ['Front Squats', 'Leg Curls', 'Lunges', 'Leg Extensions']]
        ];
    }

    private function get5DayWeightGainPlan(): array
    {
        return [
            'monday' => ['focus' => 'Chest & Back Strength', 'exercises' => ['Bench Press', 'Pull-ups', 'Incline Press', 'Rows']],
            'tuesday' => ['focus' => 'Legs Strength', 'exercises' => ['Squats', 'Romanian Deadlifts', 'Leg Press', 'Calf Raises']],
            'wednesday' => ['focus' => 'Shoulders & Arms', 'exercises' => ['Overhead Press', 'Lateral Raises', 'Bicep Curls', 'Tricep Extensions']],
            'thursday' => ['focus' => 'Chest & Back Hypertrophy', 'exercises' => ['Dumbbell Press', 'Cable Rows', 'Chest Flyes', 'Lat Pulldowns']],
            'friday' => ['focus' => 'Legs Hypertrophy', 'exercises' => ['Front Squats', 'Leg Curls', 'Lunges', 'Leg Extensions']]
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

    private function get4DayMaintenancePlan(): array
    {
        return [
            'monday' => ['focus' => 'Upper Body', 'exercises' => ['Bench Press', 'Rows', 'Overhead Press', 'Bicep Curls']],
            'tuesday' => ['focus' => 'Lower Body', 'exercises' => ['Squats', 'Leg Press', 'Leg Curls', 'Calf Raises']],
            'thursday' => ['focus' => 'Push Focus', 'exercises' => ['Incline Press', 'Shoulder Press', 'Tricep Extensions']],
            'friday' => ['focus' => 'Pull Focus', 'exercises' => ['Pull-ups', 'Lat Pulldowns', 'Face Pulls', 'Hammer Curls']]
        ];
    }

    private function get5DayMaintenancePlan(): array
    {
        return [
            'monday' => ['focus' => 'Chest & Triceps', 'exercises' => ['Bench Press', 'Incline Press', 'Tricep Extensions', 'Push-ups']],
            'tuesday' => ['focus' => 'Back & Biceps', 'exercises' => ['Pull-ups', 'Rows', 'Lat Pulldowns', 'Bicep Curls']],
            'wednesday' => ['focus' => 'Legs', 'exercises' => ['Squats', 'Romanian Deadlifts', 'Leg Press', 'Calf Raises']],
            'thursday' => ['focus' => 'Shoulders & Core', 'exercises' => ['Overhead Press', 'Lateral Raises', 'Front Raises', 'Core Circuit']],
            'friday' => ['focus' => 'Full Body Conditioning', 'exercises' => ['Circuit Training', 'Cardio Intervals', 'Bodyweight Exercises']]
        ];
    }

    // Additional plan templates (you can add more as needed)
    private function get4DayWeightLossPlan(): array
    {
        return [
            'monday' => ['focus' => 'Full Body Strength', 'exercises' => ['Squats', 'Push-ups', 'Rows', 'Lunges']],
            'tuesday' => ['focus' => 'HIIT Cardio', 'exercises' => ['Interval Training', 'Bodyweight Circuits']],
            'thursday' => ['focus' => 'Full Body Circuit', 'exercises' => ['Deadlifts', 'Overhead Press', 'Pull-ups', 'Step-ups']],
            'friday' => ['focus' => 'Steady State Cardio', 'exercises' => ['45min Cardio', 'Stretching']],
            'weekend' => ['focus' => 'Active Recovery', 'exercises' => ['Walking', 'Yoga']]
        ];
    }

    private function get5DayWeightLossPlan(): array
    {
        return [
            'monday' => ['focus' => 'Upper Body + Cardio', 'exercises' => ['Bench Press', 'Rows', '30min Cardio']],
            'tuesday' => ['focus' => 'Lower Body + HIIT', 'exercises' => ['Squats', 'Lunges', 'HIIT Circuit']],
            'wednesday' => ['focus' => 'Full Body Circuit', 'exercises' => ['Circuit Training', 'Bodyweight Exercises']],
            'thursday' => ['focus' => 'Cardio Focus', 'exercises' => ['60min Cardio', 'Core Work']],
            'friday' => ['focus' => 'Strength Endurance', 'exercises' => ['High Rep Strength', 'Conditioning']],
            'weekend' => ['focus' => 'Active Recovery', 'exercises' => ['Light Activity', 'Stretching']]
        ];
    }

    private function get6DayWeightLossPlan(): array
    {
        return [
            'monday' => ['focus' => 'Upper Body Strength', 'exercises' => ['Bench Press', 'Rows', 'Shoulder Press']],
            'tuesday' => ['focus' => 'Lower Body Strength + Cardio', 'exercises' => ['Squats', 'Deadlifts', '30min Cardio']],
            'wednesday' => ['focus' => 'HIIT + Core', 'exercises' => ['HIIT Circuit', 'Core Work']],
            'thursday' => ['focus' => 'Full Body Circuit', 'exercises' => ['Circuit Training', 'Metabolic Conditioning']],
            'friday' => ['focus' => 'Cardio Endurance', 'exercises' => ['60-75min Steady Cardio']],
            'saturday' => ['focus' => 'Active Recovery + Mobility', 'exercises' => ['Light Cardio', 'Mobility Work', 'Stretching']],
            'sunday' => ['focus' => 'Rest or Light Activity', 'exercises' => ['Optional: Walking, Yoga']]
        ];
    }

    private function get6DayMuscleBuildingPlan(): array
    {
        return [
            'monday' => ['focus' => 'Chest & Triceps', 'exercises' => ['Bench Press', 'Incline Press', 'Chest Flyes', 'Tricep Work']],
            'tuesday' => ['focus' => 'Back & Biceps', 'exercises' => ['Deadlifts', 'Pull-ups', 'Rows', 'Bicep Work']],
            'wednesday' => ['focus' => 'Legs', 'exercises' => ['Squats', 'Leg Press', 'Leg Curls', 'Calf Raises']],
            'thursday' => ['focus' => 'Shoulders & Traps', 'exercises' => ['Overhead Press', 'Lateral Raises', 'Front Raises', 'Shrugs']],
            'friday' => ['focus' => 'Arms Focus', 'exercises' => ['Arm Supersets', 'Isolation Work']],
            'saturday' => ['focus' => 'Weak Point Training', 'exercises' => ['Target Weak Areas', 'Accessory Work']],
            'sunday' => ['focus' => 'Rest', 'exercises' => ['Complete Rest']]
        ];
    }
}
