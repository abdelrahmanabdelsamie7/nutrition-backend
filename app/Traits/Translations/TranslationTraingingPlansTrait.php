<?php

namespace App\Traits\Translations;


trait TranslationTraingingPlansTrait
{
    private function translateActivityToEnglish(string $activity): string
    {
        $arabicActivities = [
            'جري' => 'running',
            'جرى' => 'running',
            'ركض' => 'jogging',
            'مشي' => 'walking',
            'مشى' => 'walking',
            'سير' => 'walking',
            'دراجة' => 'cycling',
            'دراجه' => 'cycling',
            'سباحة' => 'swimming',
            'سباحه' => 'swimming',
            'قفز الحبل' => 'jump rope',
            'حبل القفز' => 'jump rope',

            'رفع أثقال' => 'weight lifting',
            'أثقال' => 'weight lifting',
            'حديد' => 'weight lifting',
            'جيم' => 'gym',
            'تمرين' => 'exercise',
            'تمارين' => 'exercises',
            'ضغط' => 'push ups',
            'سحب' => 'pull ups',
            'بطن' => 'abs',
            'كرنش' => 'crunch',
            'سكوات' => 'squats',
            'قرفصاء' => 'squats',
            'دفع' => 'push',
            'جر' => 'pull',

            'كرة سلة' => 'basketball',
            'سله' => 'basketball',
            'كرة قدم' => 'soccer',
            'تنس' => 'tennis',
            'سبورت' => 'sports',

            'يوجا' => 'yoga',
            'بيلاتس' => 'pilates',
            'تمارين تمدد' => 'stretching',
            'رقص' => 'dancing',
            'تنزه' => 'hiking',
            'مشي سريع' => 'brisk walking',
            'ركوب الدراجات' => 'cycling',
            'سباق' => 'running',
        ];

        $activityLower = mb_strtolower($activity, 'UTF-8');

        foreach ($arabicActivities as $arabic => $english) {
            $arabicLower = mb_strtolower($arabic, 'UTF-8');
            if (str_contains($activityLower, $arabicLower)) {
                return $english;
            }
        }

        if (!preg_match('/[اأإآء-ي]/u', $activity)) {
            return $activity;
        }

        return 'walking';
    }

    private function translateRecommendationsToArabic(array $recommendations): array
    {
        $translations = [
            'goal' => 'الهدف',
            'frequency' => 'التكرار',
            'split' => 'التقسيم',
            'strength_training' => 'تدريب القوة',
            'focus' => 'التركيز',
            'volume' => 'الحجم',
            'intensity' => 'الشدة',
            'rest_periods' => 'فترات الراحة',
            'cardio' => 'الكارديو',
            'type' => 'النوع',
            'duration' => 'المدة',
            'purpose' => 'الغرض',
            'nutrition_focus' => 'التركيز الغذائي',
            'calorie_surplus' => 'فائض السعرات',
            'protein_intake' => 'استهلاك البروتين',
            'carbohydrates' => 'الكربوهيدرات',
            'meal_frequency' => 'تكرار الوجبات',
            'progression' => 'التطور',
            'recovery' => 'الاستشفاء',
            'supplementation' => 'المكملات',
            'recommended' => 'موصى بها',
            'optional' => 'اختياري',
            'workout_structure' => 'هيكل التمرين',
            'strength' => 'القوة',
            'flexibility' => 'المرونة',
            'exercises' => 'التمارين',
            'macronutrient_balance' => 'توازن المغذيات الكبرى',
            'periodization' => 'التدوير',
            'advanced_options' => 'خيارات متقدمة',
            'deload_weeks' => 'أسابيع التخفيف',
            'skill_work' => 'تدريب المهارات',
            'consistency_tips' => 'نصائح للانتظام',
            'activity_level_adjustment' => 'تعديل مستوى النشاط',
        ];

        $translated = [];

        foreach ($recommendations as $key => $value) {
            $arabicKey = $translations[$key] ?? $key;

            if (is_array($value)) {
                $translated[$arabicKey] = $this->translateRecommendationsToArabic($value);
            } else {
                $translated[$arabicKey] = $value;
            }
        }

        return $translated;
    }

    private function translatePlanToArabic(array $plan): array
    {
        $translations = [
            'monday' => 'الاثنين',
            'tuesday' => 'الثلاثاء',
            'wednesday' => 'الأربعاء',
            'thursday' => 'الخميس',
            'friday' => 'الجمعة',
            'saturday' => 'السبت',
            'sunday' => 'الأحد',
            'weekend' => 'عطلة نهاية الأسبوع',

            'focus' => 'التركيز',
            'exercises' => 'التمارين',
            'Chest & Triceps' => 'الصدر والترايسبس',
            'Back & Biceps' => 'الظهر والبايسبس',
            'Legs' => 'الأرجل',
            'Shoulders & Arms' => 'الأكتاف والأذرع',
            'Full Body Strength + Cardio' => 'تمارين الجسم كامل + كارديو',
            'HIIT Cardio' => 'كارديو عالي الكثافة',
            'Active Recovery' => 'نشاط الاستشفاء',
            'Cardio & Core' => 'كارديو والبطن',
            'Full Body A' => 'تمارين الجسم كامل أ',
            'Full Body B' => 'تمارين الجسم كامل ب',


            'Squats' => 'تمرين القرفصاء',
            'Push-ups' => 'تمرين الضغط',
            'Rows' => 'تمرين السحب',
            '30min Cardio' => 'كارديو 30 دقيقة',
            'Interval Training' => 'تدريب متقطع',
            'Bodyweight Circuits' => 'دوائر وزن الجسم',
            'Deadlifts' => 'الرفعة المميتة',
            'Overhead Press' => 'الضغط فوق الرأس',
            'Pull-ups' => 'تمرين السحب للأعلى',
            'Bench Press' => 'تمرين البنش برس',
            'Incline Press' => 'ضغط بميل',
            'Chest Flyes' => 'تمرين الطيران للصدر',
            'Tricep Extensions' => 'تمديد الترايسبس',
            'Lat Pulldowns' => 'سحب للأسفل',
            'Bicep Curls' => 'تمرين البايسبس',
            'Lunges' => 'تمرين الإنطلاق',
            'Leg Press' => 'ضغط الأرجل',
            'Calf Raises' => 'رفع ربلة الساق',
            'Lateral Raises' => 'رفع جانبي',
            'Arm Supersets' => 'مجموعات متتالية للأذرع',
            'Planks' => 'تمرين البلانك',
            'Core Circuit' => 'دوائر البطن',
        ];

        $arabicPlan = [];

        foreach ($plan as $day => $dayPlan) {
            $arabicDay = $translations[$day] ?? $day;
            $arabicDayPlan = [];

            foreach ($dayPlan as $key => $value) {
                if ($key === 'exercises' && is_array($value)) {
                    $arabicExercises = [];
                    foreach ($value as $exercise) {
                        $arabicExercises[] = $translations[$exercise] ?? $exercise;
                    }
                    $arabicDayPlan[$translations[$key] ?? $key] = $arabicExercises;
                } elseif ($key === 'focus') {
                    $arabicDayPlan[$translations[$key] ?? $key] = $translations[$value] ?? $value;
                } else {
                    $arabicDayPlan[$translations[$key] ?? $key] = $value;
                }
            }

            $arabicPlan[$arabicDay] = $arabicDayPlan;
        }

        return $arabicPlan;
    }
}