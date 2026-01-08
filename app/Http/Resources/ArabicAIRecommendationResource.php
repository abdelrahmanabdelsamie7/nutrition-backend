<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ArabicAIRecommendationResource extends JsonResource
{
    public function toArray($request)
    {
        $translations = [
            // Keys
            'id' => 'المعرف',
            'user_id' => 'معرف المستخدم',
            'period_start' => 'بداية الفترة',
            'period_end' => 'نهاية الفترة',
            'period_type' => 'نوع الفترة',
            'period' => 'الفترة',
            'start' => 'البداية',
            'end' => 'النهاية',
            'type' => 'النوع',
            'label' => 'التسمية',
            'summary' => 'الملخص',
            'recommendations' => 'التوصيات',
            'nutrition' => 'التغذية',
            'training' => 'التدريب',
            'goals' => 'الأهداف',
            'nutrition_analysis' => 'تحليل التغذية',
            'meal_suggestions' => 'اقتراحات الوجبات',
            'breakfast' => 'الفطور',
            'lunch' => 'الغداء',
            'dinner' => 'العشاء',
            'snacks' => 'الوجبات الخفيفة',
            'training_suggestions' => 'اقتراحات التدريب',
            'adherence_score' => 'معدل الالتزام',
            'overall_rating' => 'التقييم العام',
            'improvement_areas' => 'مجالات التحسين',
            'ai_model' => 'نموذج الذكاء الاصطناعي',
            'is_active' => 'نشط',
            'is_helpful' => 'مفيد',
            'user_feedback' => 'ملاحظات المستخدم',
            'viewed_at' => 'تم المشاهدة في',
            'created_at' => 'تم الإنشاء في',
            'updated_at' => 'تم التحديث في',
            'language' => 'اللغة',
            
            // Period types
            'weekly' => 'أسبوعي',
            'monthly' => 'شهري',
            
            // Values (if stored separately)
            'Based on your goal to maintain' => 'بناءً على هدفك في الحفاظ على الوزن',
            'Based on your goal to lose weight' => 'بناءً على هدفك في خسارة الوزن',
            'Based on your goal to build muscle' => 'بناءً على هدفك في بناء العضلات',
        ];
        
        $data = parent::toArray($request);
        $arabicData = [];
        
        foreach ($data as $key => $value) {
            $arabicKey = $translations[$key] ?? $key;
            
            if (is_array($value)) {
                if ($key === 'period' && isset($value['label'])) {
                    // Translate date label
                    $value['label'] = $this->translateDateLabel($value['label']);
                }
                
                if ($key === 'recommendations' || $key === 'meal_suggestions') {
                    // Translate content arrays
                    $value = $this->translateContent($value, $key);
                }
                
                $arabicData[$arabicKey] = $value;
            } elseif ($value === null) {
                $arabicData[$arabicKey] = 'غير محدد';
            } else {
                // Check if we have Arabic version stored
                if ($this->resource->{$key . '_ar'} ?? false) {
                    $arabicData[$arabicKey] = $this->resource->{$key . '_ar'};
                } else {
                    $arabicData[$arabicKey] = $translations[$value] ?? $value;
                }
            }
        }
        
        // Add language info
        $arabicData['اللغة'] = 'ar';
        $arabicData['الاتجاه'] = 'rtl';
        
        return $arabicData;
    }
    
    private function translateDateLabel(string $label): string
    {
        $monthTranslations = [
            'Jan' => 'يناير', 'Feb' => 'فبراير', 'Mar' => 'مارس',
            'Apr' => 'أبريل', 'May' => 'مايو', 'Jun' => 'يونيو',
            'Jul' => 'يوليو', 'Aug' => 'أغسطس', 'Sep' => 'سبتمبر',
            'Oct' => 'أكتوبر', 'Nov' => 'نوفمبر', 'Dec' => 'ديسمبر'
        ];
        
        foreach ($monthTranslations as $english => $arabic) {
            $label = str_replace($english, $arabic, $label);
        }
        
        // Replace "Week of" with "أسبوع"
        $label = str_replace('Week of', 'أسبوع', $label);
        
        return $label;
    }
    
    private function translateContent(array $content, string $type): array
    {
        $translations = [
            'nutrition' => [
                'Track calories to maintain current weight' => 'تتبع السعرات الحرارية للحفاظ على الوزن الحالي',
                'Balance macronutrients for optimal health' => 'وازن المغذيات الكبرى لصحة مثالية',
                'Include variety in your diet' => 'أضف تنوعاً إلى نظامك الغذائي',
                'Listen to hunger and fullness cues' => 'استمع لإشارات الجوع والامتلاء',
            ],
            'training' => [
                'Stay consistent with 3-5 sessions weekly' => 'كن منتظماً مع 3-5 جلسات أسبوعياً',
                'Mix different types of exercise' => 'امزج بين أنواع مختلفة من التمارين',
                'Include flexibility and mobility work' => 'أضف تمارين المرونة والحركة',
            ],
            'meal_suggestions' => [
                'Balanced breakfast with protein and complex carbs' => 'فطور متوازن مع بروتين وكربوهيدرات معقدة',
                'Lean protein with vegetables and whole grains' => 'بروتين خالي من الدهون مع خضروات وحبوب كاملة',
                'Similar to lunch, adjust portion size' => 'مشابه للغداء، عدل حجم الحصة',
                'Greek yogurt' => 'زبادي يوناني',
                'Fruit with nuts' => 'فاكهة مع مكسرات',
                'Vegetable sticks' => 'عصي الخضروات',
            ]
        ];
        
        $translated = [];
        
        if ($type === 'recommendations') {
            foreach ($content as $category => $items) {
                $arabicCategory = $category === 'nutrition' ? 'التغذية' : 
                                ($category === 'training' ? 'التدريب' : $category);
                
                $translated[$arabicCategory] = array_map(function($item) use ($translations, $category) {
                    return $translations[$category][$item] ?? $item;
                }, $items);
            }
        } elseif ($type === 'meal_suggestions') {
            foreach ($content as $meal => $suggestion) {
                $arabicMeal = $meal === 'breakfast' ? 'الفطور' :
                            ($meal === 'lunch' ? 'الغداء' :
                            ($meal === 'dinner' ? 'العشاء' : $meal));
                
                if (is_array($suggestion)) {
                    $translated[$arabicMeal] = array_map(function($item) use ($translations) {
                        return $translations['meal_suggestions'][$item] ?? $item;
                    }, $suggestion);
                } else {
                    $translated[$arabicMeal] = $translations['meal_suggestions'][$suggestion] ?? $suggestion;
                }
            }
        }
        
        return $translated;
    }
}