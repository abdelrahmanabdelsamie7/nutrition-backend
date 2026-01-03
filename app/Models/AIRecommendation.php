<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIRecommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'period_start',
        'period_end',
        'period_type',
        'summary',
        'recommendations',
        'nutrition_analysis',
        'meal_suggestions',
        'training_suggestions',
        'adherence_score',
        'overall_rating',
        'improvement_areas',
        'ai_model',
        'ai_parameters',
        'prompt_used',
        'raw_ai_response',
        'is_helpful',
        'user_feedback',
        'is_active',
        'viewed_at',
    ];
    protected $casts = [
        'period_end' => 'date',
        'recommendations' => 'array',
        'nutrition_analysis' => 'array',
        'meal_suggestions' => 'array',
        'training_suggestions' => 'array',
        'adherence_score' => 'decimal:2',
        'improvement_areas' => 'array',
        'ai_parameters' => 'array',
        'prompt_used' => 'array',
        'is_helpful' => 'boolean',
        'is_active' => 'boolean',
        'viewed_at' => 'datetime',
    ];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->where('period_start', '>=', $startDate)
            ->where('period_end', '<=', $endDate);
    }
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
    public function markAsViewed(): void
    {
        if (!$this->viewed_at) {
            $this->viewed_at = now();
            $this->save();
        }
    }
    public function isCurrent(): bool
    {
        return $this->period_start <= now() && $this->period_end >= now();
    }
    public function getPeriodLabel(): string
    {
        if ($this->period_type === 'daily') {
            return $this->period_start->format('F j, Y');
        } elseif ($this->period_type === 'weekly') {
            return "Week of " . $this->period_start->format('M j');
        } else {
            return $this->period_start->format('F Y');
        }
    }
}