<?php

namespace App\DTOs;

class NutritionDataDTO
{
    public function __construct(
        public int $userId,
        public string $rawInput,
        public array $parsedItems,
        public float $calories,
        public float $protein,
        public float $carbs,
        public float $fat,
        public string $source = 'manual',
        public ?string $mealType = null,
        public ?array $apiResponse = null,
        public ?string $apiSource = null,
        public bool $isCached = false,
        public ?string $cacheKey = null,
        public ?string $loggedAt = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['user_id'],
            rawInput: $data['raw_input'],
            parsedItems: $data['parsed_items'] ?? [],
            calories: $data['calories'],
            protein: $data['protein'] ?? 0,
            carbs: $data['carbs'] ?? 0,
            fat: $data['fat'] ?? 0,
            source: $data['source'] ?? 'manual',
            mealType: $data['meal_type'] ?? null,
            apiResponse: $data['api_response'] ?? null,
            apiSource: $data['api_source'] ?? null,
            isCached: $data['is_cached'] ?? false,
            cacheKey: $data['cache_key'] ?? null,
            loggedAt: $data['logged_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'raw_input' => $this->rawInput,
            'parsed_items' => $this->parsedItems,
            'calories' => $this->calories,
            'protein' => $this->protein,
            'carbs' => $this->carbs,
            'fat' => $this->fat,
            'source' => $this->source,
            'meal_type' => $this->mealType,
            'api_response' => $this->apiResponse,
            'api_source' => $this->apiSource,
            'is_cached' => $this->isCached,
            'cache_key' => $this->cacheKey,
            'logged_at' => $this->loggedAt,
        ];
    }

    public function getMacrosPercentage(): array
    {
        if ($this->calories <= 0) {
            return ['protein' => 0, 'carbs' => 0, 'fat' => 0];
        }

        return [
            'protein' => (($this->protein * 4) / $this->calories) * 100,
            'carbs' => (($this->carbs * 4) / $this->calories) * 100,
            'fat' => (($this->fat * 9) / $this->calories) * 100,
        ];
    }

    public function getCaloriesFromMacros(): float
    {
        return ($this->protein * 4) + ($this->carbs * 4) + ($this->fat * 9);
    }
}
