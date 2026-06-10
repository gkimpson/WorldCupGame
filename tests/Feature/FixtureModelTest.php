<?php

use App\Enums\FixtureStage;
use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Team;

it('belongs to a home team', function () {
    $fixture = Fixture::factory()->create();

    expect($fixture->homeTeam)->toBeInstanceOf(Team::class);
});

it('belongs to an away team', function () {
    $fixture = Fixture::factory()->create();

    expect($fixture->awayTeam)->toBeInstanceOf(Team::class);
});

it('allows null team relationships for knockout fixtures', function () {
    $fixture = Fixture::factory()->knockout()->create();

    expect($fixture->home_team_id)->toBeNull()
        ->and($fixture->away_team_id)->toBeNull()
        ->and($fixture->homeTeam)->toBeNull()
        ->and($fixture->awayTeam)->toBeNull();
});

it('casts stage to FixtureStage enum', function () {
    $fixture = Fixture::factory()->create(['stage' => FixtureStage::GroupStage]);

    expect($fixture->stage)->toBe(FixtureStage::GroupStage);
});

it('casts status to FixtureStatus enum', function () {
    $fixture = Fixture::factory()->create(['status' => FixtureStatus::Scheduled]);

    expect($fixture->status)->toBe(FixtureStatus::Scheduled);
});

it('fills score columns in completed state', function () {
    $fixture = Fixture::factory()->completed()->create();

    expect($fixture->status)->toBe(FixtureStatus::Completed)
        ->and($fixture->home_score)->toBeInt()
        ->and($fixture->away_score)->toBeInt();
});
