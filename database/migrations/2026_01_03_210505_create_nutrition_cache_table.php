<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_cache', function (Blueprint $table) {
            $table->id();

            $table->string('food_query_hash', 64)->unique();
            $table->text('food_query');

            // Nutrition data
            $table->json('nutrition_data');
            $table->json('parsed_items')->nullable();

            // Source & metadata
            $table->string('api_source')->default('calorieninjas');
            $table->json('api_response')->nullable();

            // Cache management
            $table->integer('hit_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('cached_at')->useCurrent();
            $table->timestamp('expires_at');

            $table->timestamps();

            // Indexes
            $table->index('food_query_hash');
            $table->index('expires_at');
            $table->index('last_accessed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_cache');
    }
};
