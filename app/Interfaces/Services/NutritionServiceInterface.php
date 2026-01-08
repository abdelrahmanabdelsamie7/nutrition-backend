<?php

namespace App\Interfaces\Services;

use App\DTOs\NutritionDataDTO;
use Illuminate\Http\UploadedFile;

interface NutritionServiceInterface
{
    public function processTextInput(string $text, string $userId): NutritionDataDTO;
    public function processVoiceInput(UploadedFile $audioFile, string $userId): NutritionDataDTO;
    // public function getNutritionFromExternalAPI(string $foodQuery): array;
    public function parseFoodItems(string $text): array;
    // public function calculateTotals(array $foodItems): array;
    public function getDailySummary(string $userId, string $date): array;
    public function getWeeklySummary(string $userId, string $startDate, string $endDate): array;
    // public function compareWithTargets(string $userId, array $intake): array;
}