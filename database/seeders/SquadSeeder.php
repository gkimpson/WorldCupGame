<?php

namespace Database\Seeders;

use App\Enums\PlayerPosition;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SquadSeeder extends Seeder
{
    /**
     * Run the database seeds from the committed squad data file.
     */
    public function run(): void
    {
        $this->seedFromFile(resource_path('data/squads.json'));
    }

    /**
     * Upsert teams and their players from a normalised squads JSON file.
     *
     * The file is an array of teams, each shaped as:
     * { name, code?, group?, confederation?, flag_url?, players: [ { name, position, shirt_number?, date_of_birth? } ] }
     * Player `position` holds the raw BBC label and is normalised via PlayerPosition::fromBbc().
     */
    public function seedFromFile(string $path): void
    {
        if (! is_file($path)) {
            $this->command->warn("Squad data file not found: {$path}");

            return;
        }

        /** @var array<int, array<string, mixed>> $squads */
        $squads = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        foreach ($squads as $squad) {
            $team = Team::updateOrCreate(
                ['name' => $squad['name']],
                [
                    'code' => $squad['code'] ?? null,
                    'slug' => Str::slug($squad['name']),
                    'group' => $squad['group'] ?? null,
                    'confederation' => $squad['confederation'] ?? null,
                    'flag_url' => $squad['flag_url'] ?? null,
                ],
            );

            foreach ($squad['players'] ?? [] as $player) {
                Player::updateOrCreate(
                    ['team_id' => $team->id, 'name' => $player['name']],
                    [
                        'position' => PlayerPosition::fromBbc($player['position']),
                        'shirt_number' => $player['shirt_number'] ?? null,
                        'date_of_birth' => $player['date_of_birth'] ?? null,
                    ],
                );
            }
        }
    }
}
