<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_weekly_stats', function (Blueprint $table) {
            $table->ulid('id')->charset('ascii')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('week_number');
            $table->unsignedInteger('total_points')->default(0);
            $table->unsignedSmallInteger('predictions_made')->default(0);
            $table->unsignedSmallInteger('correct_outcomes')->default(0);
            $table->unsignedSmallInteger('exact_scores')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'week_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_weekly_stats');
    }
};
