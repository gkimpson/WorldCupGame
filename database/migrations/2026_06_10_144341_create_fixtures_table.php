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
        Schema::create('fixtures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('away_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('home_team_placeholder')->nullable();
            $table->string('away_team_placeholder')->nullable();
            $table->string('stage');
            $table->char('group', 1)->nullable();
            $table->unsignedSmallInteger('match_number')->nullable()->unique();
            $table->string('venue')->nullable();
            $table->string('city')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status')->default('scheduled');
            $table->unsignedTinyInteger('home_score')->nullable();
            $table->unsignedTinyInteger('away_score')->nullable();
            $table->unsignedTinyInteger('home_score_aet')->nullable();
            $table->unsignedTinyInteger('away_score_aet')->nullable();
            $table->unsignedTinyInteger('home_score_pens')->nullable();
            $table->unsignedTinyInteger('away_score_pens')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixtures');
    }
};
