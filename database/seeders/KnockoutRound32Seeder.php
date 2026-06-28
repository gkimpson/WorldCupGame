<?php

namespace Database\Seeders;

use App\Models\Fixture;
use App\Models\Team;
use Illuminate\Database\Seeder;

class KnockoutRound32Seeder extends Seeder
{
    public function run(): void
    {
        $matchups = [
            ['home' => 'Germany', 'away' => 'Paraguay'],
            ['home' => 'France', 'away' => 'Sweden'],
            ['home' => 'South Africa', 'away' => 'Canada'],
            ['home' => 'Netherlands', 'away' => 'Morocco'],
            ['home' => 'Portugal', 'away' => 'Croatia'],
            ['home' => 'Spain', 'away' => 'Austria'],
            ['home' => 'United States', 'away' => 'Bosnia and Herzegovina'],
            ['home' => 'Belgium', 'away' => 'Senegal'],
            ['home' => 'Brazil', 'away' => 'Japan'],
            ['home' => 'Ivory Coast', 'away' => 'Norway'],
            ['home' => 'Mexico', 'away' => 'Ecuador'],
            ['home' => 'England', 'away' => 'DR Congo'],
            ['home' => 'Argentina', 'away' => 'Cape Verde'],
            ['home' => 'Australia', 'away' => 'Egypt'],
            ['home' => 'Switzerland', 'away' => 'Algeria'],
            ['home' => 'Colombia', 'away' => 'Ghana'],
        ];

        $fixtures = Fixture::where('stage', 'round_of_32')
            ->orderBy('match_number')
            ->get();

        foreach ($matchups as $index => $matchup) {
            if (! isset($fixtures[$index])) {
                continue;
            }

            $homeTeam = Team::where('name', $matchup['home'])->first();
            $awayTeam = Team::where('name', $matchup['away'])->first();

            if (! $homeTeam || ! $awayTeam) {
                $this->command->warn("Teams not found for {$matchup['home']} vs {$matchup['away']}");
                continue;
            }

            $fixtures[$index]->update([
                'home_team_id' => $homeTeam->id,
                'away_team_id' => $awayTeam->id,
            ]);

            $this->command->info("Updated fixture {$fixtures[$index]->match_number}: {$homeTeam->name} vs {$awayTeam->name}");
        }
    }
}
