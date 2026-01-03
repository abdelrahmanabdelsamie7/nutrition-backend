<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');

            // Input data
            $table->text('raw_input');
            $table->json('parsed_items')->nullable();

            // Nutrition data
            $table->decimal('calories', 8, 2);
            $table->decimal('protein', 8, 2);
            $table->decimal('carbs', 8, 2);
            $table->decimal('fat', 8, 2);
            $table->decimal('fiber', 8, 2)->nullable();
            $table->decimal('sugar', 8, 2)->nullable();

            // Metadata
            $table->enum('source', ['voice', 'manual', 'api'])->default('manual');
            $table->enum('meal_type', ['breakfast', 'lunch', 'dinner', 'snack'])->nullable();
            $table->string('food_time')->nullable();

            // External API data
            $table->json('api_response')->nullable(); // Raw API response
            $table->string('api_source')->nullable(); // e.g., 'calorieninjas'
            $table->timestamp('api_called_at')->nullable();

            // Caching
            $table->boolean('is_cached')->default(false);
            $table->string('cache_key')->nullable();

            $table->timestamp('logged_at')->useCurrent();
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'logged_at']);
            $table->index('meal_type');
            $table->index('source');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_logs');
    }
};
