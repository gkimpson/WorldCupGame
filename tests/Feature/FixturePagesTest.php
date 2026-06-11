<?php

use App\Enums\FixtureStage;
use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\Team;
use App\Models\User;

it('requires authentication to view fixtures', function () {
    $this->get(route('fixtures.index'))->assertRedirect(route('login'));
});

it('lists fixtures grouped by stage', function () {
    $user = User::factory()->create();
    $homeTeam = Team::factory()->create(['name' => 'Canada', 'flag_code' => 'ca']);
    $awayTeam = Team::factory()->create(['name' => 'Mexico', 'flag_code' => 'mx']);
    $fixture = Fixture::factory()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'stage' => FixtureStage::GroupStage,
        'match_number' => 12,
        'scheduled_at' => now()->addDays(3),
    ]);

    $this->actingAs($user)
        ->get(route('fixtures.index'))
        ->assertSuccessful()
        ->assertSee('Fixtures')
        ->assertSee('Group Stage')
        ->assertSee('Match 12')
        ->assertSee('Canada')
        ->assertSee('Mexico')
        ->assertSee(route('fixtures.show', $fixture));
});

it('shows the signed in users scored prediction on the fixture list', function () {
    $user = User::factory()->create();
    $homeTeam = Team::factory()->create(['name' => 'Japan', 'flag_code' => 'jp']);
    $awayTeam = Team::factory()->create(['name' => 'Spain', 'flag_code' => 'es']);

    $exactFixture = Fixture::factory()->completed()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'scheduled_at' => now()->subDays(3),
    ]);
    $outcomeFixture = Fixture::factory()->completed()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'scheduled_at' => now()->subDays(2),
    ]);
    $wrongFixture = Fixture::factory()->completed()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'scheduled_at' => now()->subDay(),
    ]);

    Prediction::factory()->withPoints(3)->create([
        'user_id' => $user->id,
        'fixture_id' => $exactFixture->id,
    ]);
    Prediction::factory()->withPoints(1)->create([
        'user_id' => $user->id,
        'fixture_id' => $outcomeFixture->id,
    ]);
    Prediction::factory()->withPoints(0)->create([
        'user_id' => $user->id,
        'fixture_id' => $wrongFixture->id,
    ]);

    $this->actingAs($user)
        ->get(route('fixtures.index'))
        ->assertSuccessful()
        ->assertSee('3 pts')
        ->assertSee('1 pt')
        ->assertSee('0 pts');
});

it('creates a default nil nil prediction after the prediction deadline', function () {
    $user = User::factory()->create();
    $lockedFixture = Fixture::factory()->create([
        'scheduled_at' => now()->addMinutes(90),
    ]);
    $futureFixture = Fixture::factory()->create([
        'scheduled_at' => now()->addHours(3),
    ]);

    $this->actingAs($user)
        ->get(route('fixtures.index'))
        ->assertSuccessful()
        ->assertSee('Pick 0-0');

    $this->assertDatabaseHas('predictions', [
        'user_id' => $user->id,
        'fixture_id' => $lockedFixture->id,
        'home_score' => 0,
        'away_score' => 0,
        'points' => null,
    ]);

    $this->assertDatabaseMissing('predictions', [
        'user_id' => $user->id,
        'fixture_id' => $futureFixture->id,
    ]);
});

it('shows fixture details with the signed in users prediction', function () {
    $user = User::factory()->create();
    $homeTeam = Team::factory()->create(['name' => 'United States', 'flag_code' => 'us']);
    $awayTeam = Team::factory()->create(['name' => 'Germany', 'flag_code' => 'de']);
    $fixture = Fixture::factory()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'status' => FixtureStatus::Completed,
        'home_score' => 2,
        'away_score' => 1,
        'venue' => 'MetLife Stadium',
        'city' => 'East Rutherford',
        'scheduled_at' => now()->subDay(),
    ]);

    Prediction::factory()->withPoints(4)->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
        'home_score' => 1,
        'away_score' => 1,
    ]);

    Prediction::factory()->create([
        'fixture_id' => $fixture->id,
        'home_score' => 3,
        'away_score' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('fixtures.show', $fixture))
        ->assertSuccessful()
        ->assertSee('United States')
        ->assertSee('Germany')
        ->assertSee('2 - 1')
        ->assertSee('MetLife Stadium')
        ->assertSee('East Rutherford')
        ->assertSee('Your Prediction')
        ->assertSee('1 - 1')
        ->assertSee('4 points')
        ->assertSee('2 picks');
});
