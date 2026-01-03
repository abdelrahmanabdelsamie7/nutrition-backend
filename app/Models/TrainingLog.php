<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingLog extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'activity_name',
        'activity_type',
        'duration',
        'intensity_level',
        'estimated_calories_burned',
        'calorie_calculation_meta',
        'reps',
        'sets',
        'weight_used',
        'distance',
        'heart_rate_avg',
        'notes',
        'media_urls',
        'performed_at',
    ];
    protected $casts = [
        'duration' => 'integer',
        'intensity_level' => 'decimal:1',
        'estimated_calories_burned' => 'decimal:2',
        'calorie_calculation_meta' => 'array',
        'reps' => 'integer',
        'sets' => 'integer',
        'weight_used' => 'decimal:2',
        'distance' => 'decimal:2',
        'heart_rate_avg' => 'integer',
        'media_urls' => 'array',
        'performed_at' => 'datetime',
    ];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('performed_at', today());
    }
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('performed_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }
    public function scopeByActivityType($query, $activityType)
    {
        return $query->where('activity_type', $activityType);
    }

    public function getCaloriesPerMinute(): float
    {
        if ($this->duration <= 0) {
            return 0;
        }
        return $this->estimated_calories_burned / $this->duration;
    }
    public function getIntensityDescription(): string
    {
        $level = $this->intensity_level ?? 5;
        if ($level <= 3) return 'Light';
        if ($level <= 6) return 'Moderate';
        if ($level <= 8) return 'Vigorous';
        return 'Maximum';
    }
}
