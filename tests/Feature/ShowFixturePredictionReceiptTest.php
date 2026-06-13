<?php

use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\Team;
use App\Models\User;

it('shows Exact Score badge when prediction matches result exactly', function () {
    $user = User::factory()->create();
    $home = Team::factory()->create(['name' => 'Brazil']);
    $away = Team::factory()->create(['name' => 'France']);

    $fixture = Fixture::factory()->create([
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'status' => FixtureStatus::Completed,
        'home_score' => 2,
        'away_score' => 1,
    ]);

    Prediction::factory()->withPoints(3)->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
        'home_score' => 2,
        'away_score' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('fixtures.show', $fixture))
        ->assertSuccessful()
        ->assertSee('Exact Score');
});

it('shows Correct Outcome badge when prediction winner matches but scores differ', function () {
    $user = User::factory()->create();
    $home = Team::factory()->create(['name' => 'Brazil']);
    $away = Team::factory()->create(['name' => 'France']);

    $fixture = Fixture::factory()->create([
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'status' => FixtureStatus::Completed,
        'home_score' => 2,
        'away_score' => 1,
    ]);

    Prediction::factory()->withPoints(1)->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
        'home_score' => 1,
        'away_score' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('fixtures.show', $fixture))
        ->assertSuccessful()
        ->assertSee('Correct Outcome');
});

it('shows Incorrect badge when prediction has wrong outcome', function () {
    $user = User::factory()->create();
    $home = Team::factory()->create(['name' => 'Brazil']);
    $away = Team::factory()->create(['name' => 'France']);

    $fixture = Fixture::factory()->create([
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'status' => FixtureStatus::Completed,
        'home_score' => 2,
        'away_score' => 1,
    ]);

    Prediction::factory()->withPoints(0)->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
        'home_score' => 0,
        'away_score' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('fixtures.show', $fixture))
        ->assertSuccessful()
        ->assertSee('Incorrect');
});

it('shows locked awaiting result label when fixture is locked with no result', function () {
    $user = User::factory()->create();
    $home = Team::factory()->create(['name' => 'Brazil']);
    $away = Team::factory()->create(['name' => 'France']);

    $fixture = Fixture::factory()->adminLocked()->create([
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'home_score' => null,
        'away_score' => null,
    ]);

    Prediction::factory()->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
        'home_score' => 1,
        'away_score' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('fixtures.show', $fixture))
        ->assertSuccessful()
        ->assertSee('Locked · Awaiting result');
});

it('shows no prediction message when user has no prediction on a locked fixture', function () {
    $user = User::factory()->create();
    $home = Team::factory()->create(['name' => 'Brazil']);
    $away = Team::factory()->create(['name' => 'France']);

    $fixture = Fixture::factory()->adminLocked()->create([
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
    ]);

    $this->actingAs($user)
        ->get(route('fixtures.show', $fixture))
        ->assertSuccessful()
        ->assertSee('You have not predicted this fixture yet.');
});
