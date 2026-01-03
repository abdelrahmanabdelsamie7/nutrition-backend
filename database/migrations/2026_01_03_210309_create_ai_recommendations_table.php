<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_recommendations', function (Blueprint $table) {
            $table->uuid();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');

            // Recommendation period
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('period_type', ['daily', 'weekly', 'monthly'])->default('weekly');

            // AI Content
            $table->text('summary');
            $table->json('recommendations'); // Structured JSON
            $table->json('nutrition_analysis')->nullable();
            $table->json('meal_suggestions')->nullable();
            $table->json('training_suggestions')->nullable();

            // Metrics & Scores
            $table->decimal('adherence_score', 5, 2)->nullable(); // 0-100
            $table->enum('overall_rating', ['poor', 'fair', 'good', 'excellent'])->nullable();
            $table->json('improvement_areas')->nullable();

            // AI Metadata
            $table->string('ai_model')->nullable();
            $table->json('ai_parameters')->nullable();
            $table->json('prompt_used')->nullable();
            $table->text('raw_ai_response')->nullable();

            // User feedback
            $table->boolean('is_helpful')->nullable();
            $table->text('user_feedback')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('viewed_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'period_start', 'period_end']);
            $table->index('period_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_recommendations');
    }
};
