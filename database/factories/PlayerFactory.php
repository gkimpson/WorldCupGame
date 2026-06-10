<?php

namespace Database\Factories;

use App\Enums\PlayerPosition;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->name('male'),
            'position' => fake()->randomElement(PlayerPosition::cases()),
            'shirt_number' => fake()->numberBetween(1, 26),
            'date_of_birth' => fake()->dateTimeBetween('-40 years', '-17 years'),
        ];
    }

    /**
     * Indicate that the player is a goalkeeper.
     */
    public function goalkeeper(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => PlayerPosition::Goalkeeper,
        ]);
    }
}
