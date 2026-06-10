<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserStat>
 */
class UserStatFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'total_points' => 0,
            'predictions_made' => 0,
        ];
    }

    public function withPoints(int $points, int $predictionsMade = 10): static
    {
        return $this->state([
            'total_points' => $points,
            'predictions_made' => $predictionsMade,
        ]);
    }
}
