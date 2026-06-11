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
        Schema::table('user_stats', function (Blueprint $table) {
            $table->unsignedSmallInteger('correct_outcomes')->default(0)->after('predictions_made');
            $table->unsignedSmallInteger('exact_scores')->default(0)->after('correct_outcomes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_stats', function (Blueprint $table) {
            $table->dropColumn(['correct_outcomes', 'exact_scores']);
        });
    }
};
