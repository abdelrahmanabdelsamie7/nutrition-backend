<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');

            // Training details
            $table->string('activity_name');
            $table->enum('activity_type', [
                'cardio',
                'strength',
                'flexibility',
                'sports',
                'hiit',
                'other'
            ])->default('other');

            $table->integer('duration');
            $table->decimal('intensity_level', 3, 1)->nullable();

            // Calories estimation
            $table->decimal('estimated_calories_burned', 8, 2);
            $table->json('calorie_calculation_meta')->nullable();

            // Additional metrics
            $table->integer('reps')->nullable();
            $table->integer('sets')->nullable();
            $table->decimal('weight_used', 8, 2)->nullable(); // in kg
            $table->decimal('distance', 8, 2)->nullable(); // in km
            $table->integer('heart_rate_avg')->nullable();

            // Notes & Media
            $table->text('notes')->nullable();
            $table->json('media_urls')->nullable(); // Array of image/video URLs

            $table->timestamp('performed_at')->useCurrent();
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'performed_at']);
            $table->index('activity_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_logs');
    }
};