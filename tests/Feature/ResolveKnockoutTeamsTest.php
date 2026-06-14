<?php

use App\Enums\FixtureStage;
use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Team;
use Illuminate\Support\Facades\Http;

function geminiKnockoutResponse(array $assignments): array
{
    return [
        'candidates' => [
            [
                'content' => [
                    'parts' => [
                        ['text' => json_encode($assignments)],
                    ],
                ],
            ],
        ],
    ];
}

function completedGroupStageFixture(): Fixture
{
    return Fixture::factory()->create([
        'stage' => FixtureStage::GroupStage,
        'status' => FixtureStatus::Completed,
        'home_score' => 2,
        'away_score' => 1,
    ]);
}

function knockoutFixture(FixtureStage $stage = FixtureStage::RoundOf32): Fixture
{
    return Fixture::factory()->create([
        'stage' => $stage,
        'status' => FixtureStatus::Scheduled,
        'home_team_id' => null,
        'away_team_id' => null,
        'home_team_placeholder' => 'TBD Home (Round of 32 #1)',
        'away_team_placeholder' => 'TBD Away (Round of 32 #1)',
    ]);
}

it('assigns teams to a knockout fixture when group stage is complete', function () {
    completedGroupStageFixture();

    $home = Team::factory()->create(['name' => 'Mexico']);
    $away = Team::factory()->create(['name' => 'France']);
    $fixture = knockoutFixture();

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(geminiKnockoutResponse([
            ['fixture_id' => $fixture->id, 'home_team' => 'Mexico', 'away_team' => 'France'],
        ])),
    ]);

    $this->artisan('world-cup:resolve-knockout-teams')->assertExitCode(0);

    $fixture->refresh();
    expect($fixture->home_team_id)->toBe($home->id);
    expect($fixture->away_team_id)->toBe($away->id);
});

it('resolves team name aliases to canonical DB names', function () {
    completedGroupStageFixture();

    $home = Team::factory()->create(['name' => 'Turkey']);
    $away = Team::factory()->create(['name' => 'South Korea']);
    $fixture = knockoutFixture();

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(geminiKnockoutResponse([
            ['fixture_id' => $fixture->id, 'home_team' => 'Türkiye', 'away_team' => 'Korea Republic'],
        ])),
    ]);

    $this->artisan('world-cup:resolve-knockout-teams')->assertExitCode(0);

    $fixture->refresh();
    expect($fixture->home_team_id)->toBe($home->id);
    expect($fixture->away_team_id)->toBe($away->id);
});

it('does not write to the database in dry-run mode', function () {
    completedGroupStageFixture();

    Team::factory()->create(['name' => 'Mexico']);
    Team::factory()->create(['name' => 'France']);
    $fixture = knockoutFixture();

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(geminiKnockoutResponse([
            ['fixture_id' => $fixture->id, 'home_team' => 'Mexico', 'away_team' => 'France'],
        ])),
    ]);

    $this->artisan('world-cup:resolve-knockout-teams --dry-run')->assertExitCode(0);

    $fixture->refresh();
    expect($fixture->home_team_id)->toBeNull();
    expect($fixture->away_team_id)->toBeNull();
});

it('rejects the command when group stage is not fully complete', function () {
    Fixture::factory()->create([
        'stage' => FixtureStage::GroupStage,
        'status' => FixtureStatus::Scheduled,
    ]);

    knockoutFixture();

    Http::fake();

    $this->artisan('world-cup:resolve-knockout-teams')->assertExitCode(1);

    Http::assertNothingSent();
});

it('exits early when all fixtures for the stage already have teams assigned', function () {
    completedGroupStageFixture();

    Fixture::factory()->create([
        'stage' => FixtureStage::RoundOf32,
        'home_team_id' => Team::factory()->create()->id,
        'away_team_id' => Team::factory()->create()->id,
    ]);

    Http::fake();

    $this->artisan('world-cup:resolve-knockout-teams')->assertExitCode(0);

    Http::assertNothingSent();
});

it('returns failure when a team name from Gemini cannot be resolved in the DB', function () {
    completedGroupStageFixture();

    $fixture = knockoutFixture();

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(geminiKnockoutResponse([
            ['fixture_id' => $fixture->id, 'home_team' => 'Atlantis FC', 'away_team' => 'Moon United'],
        ])),
    ]);

    $this->artisan('world-cup:resolve-knockout-teams')->assertExitCode(1);

    $fixture->refresh();
    expect($fixture->home_team_id)->toBeNull();
});

it('rejects an invalid stage option', function () {
    $this->artisan('world-cup:resolve-knockout-teams --stage=group_stage')->assertExitCode(1);
    $this->artisan('world-cup:resolve-knockout-teams --stage=nonsense')->assertExitCode(1);
});

it('dumps raw gemini response in data-only mode without touching the database', function () {
    completedGroupStageFixture();
    $fixture = knockoutFixture();

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(geminiKnockoutResponse([
            ['fixture_id' => $fixture->id, 'home_team' => 'Mexico', 'away_team' => 'France'],
        ])),
    ]);

    $this->artisan('world-cup:resolve-knockout-teams --data-only')->assertExitCode(0);

    $fixture->refresh();
    expect($fixture->home_team_id)->toBeNull();
});
