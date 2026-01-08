<?php

namespace App\Traits\Translations;

trait TranslationUsingFoodDictionaryTrait  {
    private function translateUsingFoodDictionary(string $text): string
    {
        $dictionary = [
            'تفاح' => 'apple',
            'تفاحة' => 'apple',
            'موز' => 'banana',
            'موزة' => 'banana',
            'برتقال' => 'orange',
            'برتقالة' => 'orange',
            'فراولة' => 'strawberry',
            'فراول' => 'strawberries',
            'عنب' => 'grapes',
            'مانجو' => 'mango',
            'بطيخ' => 'watermelon',
            'شمام' => 'cantaloupe',
            'خوخ' => 'peach',
            'كمثرى' => 'pear',
            'أناناس' => 'pineapple',
            'ليمون' => 'lemon',
            'رمان' => 'pomegranate',
            'توت' => 'berries',
            'توت أزرق' => 'blueberries',
            'توت بري' => 'cranberries',

            // خضروات
            'خس' => 'lettuce',
            'خيار' => 'cucumber',
            'طماطم' => 'tomatoes',
            'جزر' => 'carrots',
            'بصل' => 'onions',
            'ثوم' => 'garlic',
            'بطاطس' => 'potatoes',
            'بطاطا حلوة' => 'sweet potato',
            'فلفل' => 'pepper',
            'فلفل حار' => 'chili pepper',
            'كرنب' => 'cabbage',
            'قرنبيط' => 'cauliflower',
            'بروكلي' => 'broccoli',
            'سبانخ' => 'spinach',
            'كوسا' => 'zucchini',
            'باذنجان' => 'eggplant',
            'فاصوليا' => 'beans',
            'فاصوليا خضراء' => 'green beans',
            'بازلاء' => 'peas',
            'ذرة' => 'corn',

            // بروتينات
            'دجاج' => 'chicken',
            'لحم بقر' => 'beef',
            'لحم ضأن' => 'lamb',
            'لحم' => 'meat',
            'سمك' => 'fish',
            'سلمون' => 'salmon',
            'تونة' => 'tuna',
            'روبيان' => 'shrimp',
            'جمبري' => 'prawns',
            'بيض' => 'eggs',
            'لبن' => 'yogurt',
            'زبادي' => 'yogurt',
            'جبن' => 'cheese',
            'جبنة' => 'cheese',
            'حليب' => 'milk',
            'زبدة' => 'butter',
            'قشطة' => 'cream',

            // نشويات
            'أرز' => 'rice',
            'مكرونة' => 'pasta',
            'معكرونة' => 'pasta',
            'خبز' => 'bread',
            'شعيرية' => 'noodles',
            'شوفان' => 'oats',
            'قمح' => 'wheat',
            'دقيق' => 'flour',
            'عيش' => 'bread',
            'رغيف' => 'loaf bread',

            // بقوليات
            'عدس' => 'lentils',
            'فول' => 'fava beans',
            'حمص' => 'chickpeas',

            // مكملات وتوابل
            'سكر' => 'sugar',
            'ملح' => 'salt',
            'زيت' => 'oil',
            'زيت زيتون' => 'olive oil',
            'زيتون' => 'olives',
            'عسل' => 'honey',
            'خل' => 'vinegar',
            'مايونيز' => 'mayonnaise',
            'كاتشب' => 'ketchup',
            'خردل' => 'mustard',
            'بهارات' => 'spices',
            'فلفل أسود' => 'black pepper',
            'قرفة' => 'cinnamon',
            'كركم' => 'turmeric',
            'زنجبيل' => 'ginger',

            // مشروبات
            'قهوة' => 'coffee',
            'شاي' => 'tea',
            'عصير' => 'juice',
            'عصير برتقال' => 'orange juice',
            'عصير تفاح' => 'apple juice',
            'ماء' => 'water',

            // وحدات قياس
            'كوب' => 'cup',
            'ملعقة' => 'spoon',
            'ملعقة كبيرة' => 'tablespoon',
            'ملعقة صغيرة' => 'teaspoon',
            'جرام' => 'gram',
            'كيلو' => 'kilo',
            'لتر' => 'liter',
            'ملليلتر' => 'milliliter',

            // طرق الطبخ
            'مشوي' => 'grilled',
            'مسلوق' => 'boiled',
            'مقلي' => 'fried',
            'محمر' => 'roasted',
            'نيء' => 'raw',
        ];

        $translated = $text;

        uksort($dictionary, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        foreach ($dictionary as $arabic => $english) {

            $translated = preg_replace(
                '/\b' . preg_quote($arabic, '/') . '\b/ui',
                $english,
                $translated
            );
        }

        if ($this->containsArabic($translated)) {
            $translated = $this->translateIndividualWords($translated, $dictionary);
        }

        return $translated;
    }
    private function translateIndividualWords(string $text, array $dictionary): string
    {
        $words = preg_split('/\s+/', $text);
        $translatedWords = [];

        foreach ($words as $word) {
            $word = trim($word);
            if (isset($dictionary[$word])) {
                $translatedWords[] = $dictionary[$word];
            } elseif ($this->containsArabic($word)) {
                $translatedWords[] = $word;
            } else {
                $translatedWords[] = $word;
            }
        }

        return implode(' ', $translatedWords);
    }
}
