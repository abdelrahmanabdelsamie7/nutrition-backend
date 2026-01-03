<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // Profile info
            $table->integer('age')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->decimal('height', 5, 2)->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->decimal('target_weight', 5, 2)->nullable();

            $table->decimal('bmr', 8, 2)->nullable(); // Basal Metabolic Rate
            $table->enum('bmr_formula', ['mifflin', 'harris', 'custom'])->default('mifflin');
            $table->decimal('tdee', 8, 2)->nullable(); // Total Daily Energy Expenditure

            // Goals & Preferences
            $table->enum('activity_level', ['sedentary', 'light', 'moderate', 'active', 'very_active'])->default('moderate');
            $table->enum('goal', ['lose_weight', 'maintain', 'gain_weight', 'build_muscle'])->default('maintain');
            $table->enum('diet_type', ['balanced', 'keto', 'vegetarian', 'vegan', 'mediterranean'])->default('balanced');
            $table->enum('budget_level', ['low', 'medium', 'high'])->default('medium');

            // Daily targets (calculated based on profile)
            $table->integer('daily_calorie_target')->nullable();
            $table->integer('daily_protein_target')->nullable();
            $table->integer('daily_carbs_target')->nullable();
            $table->integer('daily_fat_target')->nullable();

            // Settings
            $table->boolean('voice_enabled')->default(true);
            $table->boolean('ai_recommendations_enabled')->default(true);
            $table->enum('notification_frequency', ['never', 'daily', 'weekly'])->default('daily');

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['email', 'deleted_at']);
            $table->index('created_at');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};