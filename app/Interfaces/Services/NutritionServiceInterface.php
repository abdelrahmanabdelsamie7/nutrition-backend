<?php

namespace App\Interfaces\Services;

use App\DTOs\NutritionDataDTO;
use Illuminate\Http\UploadedFile;

interface NutritionServiceInterface
{
    public function processTextInput(string $text, int $userId): NutritionDataDTO;
    public function processVoiceInput(UploadedFile $audioFile, int $userId): NutritionDataDTO;
    public function getNutritionFromExternalAPI(string $foodQuery): array;
    public function parseFoodItems(string $text): array;
    public function calculateTotals(array $foodItems): array;
    public function getDailySummary(int $userId, string $date): array;
    public function getWeeklySummary(int $userId, string $startDate, string $endDate): array;
    public function compareWithTargets(int $userId, array $intake): array;
}