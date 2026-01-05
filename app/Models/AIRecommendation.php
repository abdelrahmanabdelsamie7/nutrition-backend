<?php

namespace App\Models;

use App\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AIRecommendation extends Model
{
    use HasFactory, UsesUuid;
    protected $table = 'ai_recommendations';

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
        'period_start' => 'date',  // هذا بيعمل string في الـ getter
        'period_end' => 'date',    // هذا برضه
        'period_type' => 'string',
        'recommendations' => 'array',
        'nutrition_analysis' => 'array',
        'meal_suggestions' => 'array',
        'training_suggestions' => 'array',
        'adherence_score' => 'decimal:2',
        'overall_rating' => 'string',
        'improvement_areas' => 'array',
        'ai_parameters' => 'array',
        'prompt_used' => 'array',
        'raw_ai_response' => 'string',
        'is_helpful' => 'boolean',
        'user_feedback' => 'string',
        'is_active' => 'boolean',
        'viewed_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
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

    /**
     * ACCESSORS - تحويل التاريخ لـ Carbon object تلقائياً
     */
    public function getPeriodStartAttribute($value): Carbon
    {
        return Carbon::parse($value);
    }

    public function getPeriodEndAttribute($value): Carbon
    {
        return Carbon::parse($value);
    }

    /**
     * MUTATORS - تأكد من حفظ التاريخ كـ Y-m-d
     */
    public function setPeriodStartAttribute($value): void
    {
        $this->attributes['period_start'] = $this->parseDate($value);
    }

    public function setPeriodEndAttribute($value): void
    {
        $this->attributes['period_end'] = $this->parseDate($value);
    }

    /**
     * Helper Methods
     */
    public function markAsViewed(): void
    {
        if (!$this->viewed_at) {
            $this->viewed_at = now();
            $this->save();
        }
    }

    public function isCurrent(): bool
    {
        $start = $this->getPeriodStartAttribute($this->attributes['period_start'] ?? null);
        $end = $this->getPeriodEndAttribute($this->attributes['period_end'] ?? null);

        return $start <= now() && $end >= now();
    }

    public function getPeriodLabel(): string
    {
        $start = $this->period_start; // Now returns Carbon object

        if ($this->period_type === 'daily') {
            return $start->format('F j, Y');
        } elseif ($this->period_type === 'weekly') {
            $end = $this->period_end;
            return "Week of " . $start->format('M j') . " - " . $end->format('M j');
        } else {
            return $start->format('F Y');
        }
    }

    public function getPeriodDuration(): int
    {
        $start = $this->period_start;
        $end = $this->period_end;

        return $start->diffInDays($end) + 1;
    }

    public function getFormattedPeriod(): array
    {
        return [
            'start' => $this->period_start->format('Y-m-d'),
            'end' => $this->period_end->format('Y-m-d'),
            'label' => $this->getPeriodLabel(),
            'type' => $this->period_type,
            'duration_days' => $this->getPeriodDuration(),
            'is_current' => $this->isCurrent(),
        ];
    }

    /**
     * Parse date from various formats
     */
    private function parseDate($value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }

        if (is_string($value)) {
            return Carbon::parse($value)->format('Y-m-d');
        }

        return now()->format('Y-m-d');
    }
}