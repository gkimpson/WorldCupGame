<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserWeeklyStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserWeeklyStat>
 */
class UserWeeklyStatFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'week_number' => fake()->numberBetween(1, 6),
            'total_points' => 0,
            'predictions_made' => 0,
            'correct_outcomes' => 0,
            'exact_scores' => 0,
        ];
    }
}
