<?php

use App\Enums\FixtureStage;
use App\Enums\FixtureStatus;
use App\Models\Fixture;
use Database\Seeders\FixtureSeeder;
use Database\Seeders\SquadSeeder;

/**
 * @param  array<int, array<string, mixed>>  $data
 */
function writeFixtureFile(array $data): string
{
    $path = sys_get_temp_dir().'/fixtures_'.uniqid().'.json';
    file_put_contents($path, json_encode($data));

    return $path;
}

it('seeds group stage fixtures with resolved team FKs', function () {
    (new SquadSeeder)->seedFromFile(resource_path('data/squads.json'));

    $path = writeFixtureFile([
        [
            'match_number' => 1,
            'stage' => 'group_stage',
            'group' => 'A',
            'home_team' => 'MEX',
            'away_team' => 'RSA',
            'home_team_placeholder' => null,
            'away_team_placeholder' => null,
            'scheduled_at' => '2026-06-11',
            'venue' => 'Estadio Azteca',
            'city' => 'Mexico City',
            'status' => 'scheduled',
        ],
    ]);

    (new FixtureSeeder)->seedFromFile($path);

    expect(Fixture::count())->toBe(1);

    $fixture = Fixture::first();
    expect($fixture->stage)->toBe(FixtureStage::GroupStage)
        ->and($fixture->group)->toBe('A')
        ->and($fixture->status)->toBe(FixtureStatus::Scheduled)
        ->and($fixture->homeTeam->code)->toBe('MEX')
        ->and($fixture->awayTeam->code)->toBe('RSA');
});

it('seeds knockout fixtures with null teams and placeholders', function () {
    $path = writeFixtureFile([
        [
            'match_number' => 73,
            'stage' => 'round_of_32',
            'group' => null,
            'home_team' => null,
            'away_team' => null,
            'home_team_placeholder' => 'Winner Group A',
            'away_team_placeholder' => 'Runner-up Group B',
            'scheduled_at' => null,
            'venue' => null,
            'city' => null,
            'status' => 'scheduled',
        ],
    ]);

    (new FixtureSeeder)->seedFromFile($path);

    $fixture = Fixture::first();
    expect($fixture->home_team_id)->toBeNull()
        ->and($fixture->away_team_id)->toBeNull()
        ->and($fixture->home_team_placeholder)->toBe('Winner Group A')
        ->and($fixture->stage)->toBe(FixtureStage::RoundOf32);
});

it('is idempotent on repeated runs', function () {
    $path = writeFixtureFile([
        [
            'match_number' => 1,
            'stage' => 'group_stage',
            'group' => 'A',
            'home_team' => null,
            'away_team' => null,
            'home_team_placeholder' => 'TBD',
            'away_team_placeholder' => 'TBD',
            'scheduled_at' => null,
            'venue' => null,
            'city' => null,
            'status' => 'scheduled',
        ],
    ]);

    (new FixtureSeeder)->seedFromFile($path);
    (new FixtureSeeder)->seedFromFile($path);

    expect(Fixture::count())->toBe(1);
});
