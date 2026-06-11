<?php

namespace Database\Factories;

use App\Enums\FixtureStage;
use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fixture>
 */
class FixtureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qualifiedIds = Team::whereNotNull('group')->pluck('id');

        return [
            'home_team_id' => $qualifiedIds->isNotEmpty() ? $qualifiedIds->random() : Team::factory(),
            'away_team_id' => $qualifiedIds->isNotEmpty() ? $qualifiedIds->random() : Team::factory(),
            'home_team_placeholder' => null,
            'away_team_placeholder' => null,
            'stage' => FixtureStage::GroupStage,
            'group' => $this->faker->randomElement(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L']),
            'match_number' => null,
            'venue' => $this->faker->company().' Stadium',
            'city' => $this->faker->city(),
            'scheduled_at' => $this->faker->dateTimeBetween('2026-06-11', '2026-07-19'),
            'status' => FixtureStatus::Scheduled,
            'is_locked' => false,
            'home_score' => null,
            'away_score' => null,
            'home_score_aet' => null,
            'away_score_aet' => null,
            'home_score_pens' => null,
            'away_score_pens' => null,
        ];
    }

    /**
     * Indicate that the fixture has been completed with a result.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FixtureStatus::Completed,
            'home_score' => $this->faker->numberBetween(0, 5),
            'away_score' => $this->faker->numberBetween(0, 5),
        ]);
    }

    /**
     * Indicate that the fixture is admin-locked and cannot accept new predictions.
     */
    public function adminLocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_locked' => true,
        ]);
    }

    /**
     * Indicate that the fixture is a knockout match with unknown teams.
     */
    public function knockout(): static
    {
        return $this->state(fn (array $attributes) => [
            'home_team_id' => null,
            'away_team_id' => null,
            'home_team_placeholder' => 'TBD',
            'away_team_placeholder' => 'TBD',
            'stage' => $this->faker->randomElement([
                FixtureStage::RoundOf32,
                FixtureStage::RoundOf16,
                FixtureStage::QuarterFinal,
                FixtureStage::SemiFinal,
            ]),
            'group' => null,
        ]);
    }
}
