<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NutritionCache extends Model
{
    use HasFactory;
    protected $table = 'nutrition_cache';
    protected $fillable = [
        'food_query_hash',
        'food_query',
        'nutrition_data',
        'parsed_items',
        'api_source',
        'api_response',
        'hit_count',
        'last_accessed_at',
        'expires_at',
    ];
    protected $casts = [
        'nutrition_data' => 'array',
        'parsed_items' => 'array',
        'api_response' => 'array',
        'last_accessed_at' => 'datetime',
        'expires_at' => 'datetime',
        'cached_at' => 'datetime',
    ];
    public function incrementHitCount(): void
    {
        $this->hit_count++;
        $this->last_accessed_at = now();
        $this->save();
    }
    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }
    public function getCacheKey(): string
    {
        return "nutrition_cache:{$this->food_query_hash}";
    }
}
