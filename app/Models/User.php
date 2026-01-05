<?php

namespace App\Models;

use App\Traits\{CalculateBmrTrait, UsesUuid};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes, CalculateBmrTrait, UsesUuid;
    protected $fillable = [
        'name',
        'email',
        'password',
        'age',
        'gender',
        'height',
        'weight',
        'target_weight',
        'bmr',
        'tdee',
        'bmr_formula',
        'activity_level',
        'goal',
        'diet_type',
        'budget_level',
        'daily_calorie_target',
        'daily_protein_target',
        'daily_carbs_target',
        'daily_fat_target',
        'voice_enabled',
        'ai_recommendations_enabled',
        'notification_frequency',
    ];
    protected $hidden = [
        'password',
        'remember_token',
        'deleted_at',
    ];
    protected $casts = [
        'email_verified_at' => 'datetime',
        'height' => 'decimal:2',
        'weight' => 'decimal:2',
        'target_weight' => 'decimal:2',
        // 'bmr' => 'decimal:2',
        // 'tdee' => 'decimal:2',
        'daily_calorie_target' => 'integer',
        'daily_protein_target' => 'integer',
        'daily_carbs_target' => 'integer',
        'daily_fat_target' => 'integer',
        'voice_enabled' => 'boolean',
        'ai_recommendations_enabled' => 'boolean',
        'parsed_items' => 'array',
        'api_response' => 'array',
        'calorie_calculation_meta' => 'array',
        'media_urls' => 'array',
        'recommendations' => 'array',
        'nutrition_analysis' => 'array',
        'meal_suggestions' => 'array',
        'training_suggestions' => 'array',
        'improvement_areas' => 'array',
        'ai_parameters' => 'array',
        'prompt_used' => 'array',
        'is_active' => 'boolean',
        'is_helpful' => 'boolean',
    ];
    public function foodLogs(): HasMany
    {
        return $this->hasMany(FoodLog::class);
    }
    public function trainingLogs(): HasMany
    {
        return $this->hasMany(TrainingLog::class);
    }
    public function aiRecommendations(): HasMany
    {
        return $this->hasMany(AIRecommendation::class);
    }
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    public function getJWTCustomClaims(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'role' => 'user',
            'has_complete_profile' => $this->hasCompleteProfile(),
            'daily_targets' => [
                'calories' => $this->daily_calorie_target,
                'protein' => $this->daily_protein_target,
                'carbs' => $this->daily_carbs_target,
                'fat' => $this->daily_fat_target,
            ]
        ];
    }
    public function updateProfile(array $data): self
    {
        $this->fill($data);
        if ($this->isDirty(['age', 'gender', 'height', 'weight', 'activity_level', 'goal'])) {
            $this->calculateDailyTargets();
        }
        $this->save();
        return $this;
    }
    public function getTodayNutritionSummary(): array
    {
        $foodLogs = $this->foodLogs()
            ->whereDate('logged_at', today())
            ->get();
        $trainingLogs = $this->trainingLogs()
            ->whereDate('performed_at', today())
            ->get();
        return [
            'food' => [
                'total_calories' => $foodLogs->sum('calories'),
                'total_protein' => $foodLogs->sum('protein'),
                'total_carbs' => $foodLogs->sum('carbs'),
                'total_fat' => $foodLogs->sum('fat'),
                'meal_count' => $foodLogs->count(),
            ],
            'training' => [
                'total_calories_burned' => $trainingLogs->sum('estimated_calories_burned'),
                'total_duration' => $trainingLogs->sum('duration'),
                'activity_count' => $trainingLogs->count(),
            ],
            'remaining' => [
                'calories' => $this->getRemainingCalories(),
                'macros' => $this->getRemainingMacros(),
            ]
        ];
    }
    public function isOnTrack(): bool
    {
        $summary = $this->getTodayNutritionSummary();
        $caloriesConsumed = $summary['food']['total_calories'];
        $targetRange = [
            'min' => $this->daily_calorie_target - 100,
            'max' => $this->daily_calorie_target + 100,
        ];
        return $caloriesConsumed >= $targetRange['min'] &&
            $caloriesConsumed <= $targetRange['max'];
    }
}
