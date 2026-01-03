<?php

namespace App\Interfaces\Repositories;

interface NutritionCacheRepositoryInterface
{
    public function get(string $queryHash): ?array;
    public function store(string $queryHash, string $query, array $nutritionData, array $parsedItems = [], string $apiSource = 'calorieninjas'): bool;
    public function exists(string $queryHash): bool;
    public function incrementHitCount(string $queryHash): void;
    public function getStatistics(): array;
    public function clearExpired(): int;
    public function getTopQueries(int $limit = 10): array;
    public function getHitRate(): float;
}
