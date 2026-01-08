<?php

namespace App\Services\Translation;

use Stichoza\GoogleTranslate\GoogleTranslate;
use App\Traits\Translations\TranslationUsingFoodDictionaryTrait;

class ArabicTranslationService
{
    use TranslationUsingFoodDictionaryTrait;
    private GoogleTranslate $translator;

    public function __construct()
    {
        $this->translator = new GoogleTranslate();
        $this->translator->setSource('ar');
        $this->translator->setTarget('en');

        $this->translator->setOptions([
            'timeout' => 10,
            'proxy' => null,
        ]);
    }

    public function translateFoodText(string $arabicText): string
    {
        try {
            $dictionaryTranslated = $this->translateUsingFoodDictionary($arabicText);

            if ($dictionaryTranslated !== $arabicText && !$this->containsArabic($dictionaryTranslated)) {
                return $dictionaryTranslated;
            }

            $translated = $this->translator->translate($arabicText);

            return $translated;
        } catch (\Exception $e) {
            return $this->translateUsingFoodDictionary($arabicText);
        }
    }

    public function containsArabic(string $text): bool
    {
        return preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
    }

    public function extractQuantity(string $text): array
    {
        $arabicNumbers = [
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9'
        ];

        $normalized = strtr($text, $arabicNumbers);
        preg_match('/(\d+(?:\.\d+)?)\s*(\p{L}*)/u', $normalized, $matches);

        if (!empty($matches[1])) {
            return [
                'quantity' => (float) $matches[1],
                'unit' => $matches[2] ?? '',
                'original_text' => $text
            ];
        }

        $fractions = [
            'نصف' => 0.5,
            'ربع' => 0.25,
            'ثلث' => 0.33,
            'نصفين' => 0.5,
            'ربعين' => 0.5,
        ];

        foreach ($fractions as $arabic => $value) {
            if (str_contains($text, $arabic)) {
                return [
                    'quantity' => $value,
                    'unit' => '',
                    'original_text' => $text
                ];
            }
        }

        return ['quantity' => 1, 'unit' => '', 'original_text' => $text];
    }
}
