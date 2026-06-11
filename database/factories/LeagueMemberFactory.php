<?php

namespace Database\Factories;

use App\Enums\LeagueMemberRole;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeagueMember>
 */
class LeagueMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'league_id' => League::factory(),
            'user_id' => User::factory(),
            'role' => LeagueMemberRole::Member,
            'joined_at' => now(),
        ];
    }

    public function owner(): static
    {
        return $this->state(['role' => LeagueMemberRole::Owner]);
    }
}
