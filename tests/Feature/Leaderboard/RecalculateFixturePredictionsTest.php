<?php

use App\Enums\FixtureStatus;
use App\Events\ResultImported;
use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;

it('awards 3 points for an exact score prediction', function () {
    $fixture = Fixture::factory()->completed()->create([
        'home_score' => 2,
        'away_score' => 1,
    ]);

    $prediction = Prediction::factory()->withPoints(99)->create([
        'fixture_id' => $fixture->id,
        'home_score' => 2,
        'away_score' => 1,
    ]);

    event(new ResultImported($fixture));

    expect($prediction->fresh()->points)->toBe(3);
});

it('awards 1 point for a correct outcome prediction', function () {
    $fixture = Fixture::factory()->completed()->create([
        'home_score' => 2,
        'away_score' => 1,
    ]);

    $prediction = Prediction::factory()->withPoints(99)->create([
        'fixture_id' => $fixture->id,
        'home_score' => 3,
        'away_score' => 0,
    ]);

    event(new ResultImported($fixture));

    expect($prediction->fresh()->points)->toBe(1);
});

it('awards 0 points for a wrong prediction', function () {
    $fixture = Fixture::factory()->completed()->create([
        'home_score' => 2,
        'away_score' => 1,
    ]);

    $prediction = Prediction::factory()->withPoints(99)->create([
        'fixture_id' => $fixture->id,
        'home_score' => 0,
        'away_score' => 2,
    ]);

    event(new ResultImported($fixture));

    expect($prediction->fresh()->points)->toBe(0);
});

it('leaves points null when the fixture is not yet completed', function () {
    $fixture = Fixture::factory()->create([
        'status' => FixtureStatus::Scheduled,
    ]);

    $prediction = Prediction::factory()->create([
        'fixture_id' => $fixture->id,
        'home_score' => 1,
        'away_score' => 0,
    ]);

    event(new ResultImported($fixture));

    expect($prediction->fresh()->points)->toBeNull();
});

it('creates and scores a default nil nil prediction for users who missed the deadline', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->completed()->create([
        'scheduled_at' => now()->subDay(),
        'home_score' => 0,
        'away_score' => 0,
    ]);

    event(new ResultImported($fixture));

    $prediction = Prediction::where('user_id', $user->id)
        ->where('fixture_id', $fixture->id)
        ->first();

    expect($prediction)->not->toBeNull()
        ->and($prediction->home_score)->toBe(0)
        ->and($prediction->away_score)->toBe(0)
        ->and($prediction->points)->toBe(3);
});

it('does not create default predictions for dummy users', function () {
    $realUser = User::factory()->create();
    $dummyUser = User::factory()->dummy()->create();
    $fixture = Fixture::factory()->completed()->create([
        'scheduled_at' => now()->subDay(),
        'home_score' => 0,
        'away_score' => 0,
    ]);

    event(new ResultImported($fixture));

    expect(Prediction::where('user_id', $realUser->id)->where('fixture_id', $fixture->id)->exists())->toBeTrue()
        ->and(Prediction::where('user_id', $dummyUser->id)->where('fixture_id', $fixture->id)->exists())->toBeFalse();
});
