<?php

namespace Database\Factories;

use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prediction>
 */
class PredictionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'fixture_id' => Fixture::factory(),
            'home_score' => $this->faker->numberBetween(0, 5),
            'away_score' => $this->faker->numberBetween(0, 5),
            'points' => null,
        ];
    }

    public function withPoints(int $points): static
    {
        return $this->state(['points' => $points]);
    }
}
