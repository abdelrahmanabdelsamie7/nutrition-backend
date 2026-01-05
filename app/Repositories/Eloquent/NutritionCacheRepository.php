<?php

namespace App\Repositories\Eloquent;

use App\Interfaces\Repositories\NutritionCacheRepositoryInterface;
use App\Models\NutritionCache;
use Illuminate\Support\Facades\{DB, Log};

class NutritionCacheRepository implements NutritionCacheRepositoryInterface
{
    public function get(string $queryHash): ?array
    {
        $cache = NutritionCache::where('food_query_hash', $queryHash)
            ->where('expires_at', '>', now())
            ->first();

        if ($cache) {
            $cache->incrementHitCount();
            return $cache->nutrition_data;
        }

        return null;
    }

    public function store(string $queryHash, string $query, array $nutritionData, array $parsedItems = [], string $apiSource = 'calorieninjas'): bool
    {
        $expiresAt = now()->addHours(24); // Cache for 24 hours

        try {
            NutritionCache::updateOrCreate(
                ['food_query_hash' => $queryHash],
                [
                    'food_query' => $query,
                    'nutrition_data' => $nutritionData,
                    'parsed_items' => $parsedItems,
                    'api_source' => $apiSource,
                    'expires_at' => $expiresAt,
                    'last_accessed_at' => now(),
                ]
            );

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to store nutrition cache', [
                'query_hash' => $queryHash,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function exists(string $queryHash): bool
    {
        return NutritionCache::where('food_query_hash', $queryHash)
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function incrementHitCount(string $queryHash): void
    {
        NutritionCache::where('food_query_hash', $queryHash)
            ->increment('hit_count');
    }

    public function getStatistics(): array
    {
        $total = NutritionCache::count();
        $active = NutritionCache::where('expires_at', '>', now())->count();
        $expired = NutritionCache::where('expires_at', '<=', now())->count();

        $topQueries = NutritionCache::orderBy('hit_count', 'desc')
            ->limit(10)
            ->get(['food_query', 'hit_count', 'last_accessed_at'])
            ->toArray();

        $sourceBreakdown = NutritionCache::select('api_source', DB::raw('COUNT(*) as count'))
            ->groupBy('api_source')
            ->get()
            ->pluck('count', 'api_source')
            ->toArray();

        return [
            'total_entries' => $total,
            'active_entries' => $active,
            'expired_entries' => $expired,
            'cache_hit_rate' => $this->getHitRate(),
            'top_queries' => $topQueries,
            'source_breakdown' => $sourceBreakdown,
            'total_hits' => NutritionCache::sum('hit_count'),
        ];
    }

    public function clearExpired(): int
    {
        return NutritionCache::where('expires_at', '<=', now())
            ->delete();
    }

    public function getTopQueries(int $limit = 10): array
    {
        return NutritionCache::orderBy('hit_count', 'desc')
            ->limit($limit)
            ->get(['food_query', 'hit_count', 'last_accessed_at', 'cached_at'])
            ->toArray();
    }

    public function getHitRate(): float
    {
        $totalHits = NutritionCache::sum('hit_count');
        $uniqueQueries = NutritionCache::count();

        if ($uniqueQueries === 0) {
            return 0;
        }

        return $totalHits / $uniqueQueries;
    }
}
