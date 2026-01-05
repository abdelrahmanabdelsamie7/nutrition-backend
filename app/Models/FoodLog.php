<?php

namespace App\Models;

use App\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodLog extends Model
{
    use HasFactory, UsesUuid;
    protected $fillable = [
        'user_id',
        'raw_input',
        'parsed_items',
        'calories',
        'protein',
        'carbs',
        'fat',
        'fiber',
        'sugar',
        'source',
        'meal_type',
        'food_time',
        'api_response',
        'api_source',
        'api_called_at',
        'is_cached',
        'cache_key',
        'logged_at',
    ];
    protected $casts = [
        'calories' => 'decimal:2',
        'protein' => 'decimal:2',
        'carbs' => 'decimal:2',
        'fat' => 'decimal:2',
        'fiber' => 'decimal:2',
        'sugar' => 'decimal:2',
        'parsed_items' => 'array',
        'api_response' => 'array',
        'is_cached' => 'boolean',
        'logged_at' => 'datetime',
        'api_called_at' => 'datetime',
    ];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function scopeToday($query)
    {
        return $query->whereDate('logged_at', today());
    }
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('logged_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }
    public function scopeByMealType($query, $mealType)
    {
        return $query->where('meal_type', $mealType);
    }
    public function scopeBySource($query, $source)
    {
        return $query->where('source', $source);
    }
    public function getMacrosPercentage(): array
    {
        $total = $this->calories;
        if ($total <= 0) {
            return [
                'protein' => 0,
                'carbs' => 0,
                'fat' => 0,
            ];
        }
        return [
            'protein' => (($this->protein * 4) / $total) * 100,
            'carbs' => (($this->carbs * 4) / $total) * 100,
            'fat' => (($this->fat * 9) / $total) * 100,
        ];
    }
    public function isFromCache(): bool
    {
        return $this->is_cached && !empty($this->cache_key);
    }
    public function getApiSourceName(): string
    {
        return $this->api_source ?? 'manual';
    }
}