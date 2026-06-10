<?php

namespace Database\Seeders;

use App\Models\Fixture;
use App\Models\Team;
use Illuminate\Database\Seeder;

class FixtureSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedFromFile(resource_path('data/fixtures.json'));
    }

    public function seedFromFile(string $path): void
    {
        if (! is_file($path)) {
            $this->command->warn("Fixtures data file not found: {$path}");

            return;
        }

        /** @var array<int, array<string, mixed>> $fixtures */
        $fixtures = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        $teamIds = Team::pluck('id', 'code');

        foreach ($fixtures as $fixture) {
            Fixture::updateOrCreate(
                ['match_number' => $fixture['match_number']],
                [
                    'home_team_id' => $fixture['home_team'] ? ($teamIds[$fixture['home_team']] ?? null) : null,
                    'away_team_id' => $fixture['away_team'] ? ($teamIds[$fixture['away_team']] ?? null) : null,
                    'home_team_placeholder' => $fixture['home_team_placeholder'],
                    'away_team_placeholder' => $fixture['away_team_placeholder'],
                    'stage' => $fixture['stage'],
                    'group' => $fixture['group'],
                    'venue' => $fixture['venue'],
                    'city' => $fixture['city'],
                    'scheduled_at' => $fixture['scheduled_at'],
                    'status' => $fixture['status'],
                ],
            );
        }
    }
}
