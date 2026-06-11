<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->country();

        return [
            'name' => $name,
            'code' => Str::upper(fake()->unique()->lexify('???')),
            'slug' => Str::slug($name),
            'group' => fake()->optional()->randomElement(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L']),
            'confederation' => fake()->optional()->randomElement(['UEFA', 'CONMEBOL', 'CONCACAF', 'CAF', 'AFC', 'OFC']),
            'flag_code' => null,
        ];
    }
}
