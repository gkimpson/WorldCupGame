<?php

use App\Events\ResultImported;
use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;
use App\Models\UserStat;

it('creates a user_stat row when a scored prediction is processed for the first time', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->completed()->create([
        'home_score' => 1,
        'away_score' => 0,
    ]);

    Prediction::factory()->withPoints(3)->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
        'home_score' => 1,
        'away_score' => 0,
    ]);

    event(new ResultImported($fixture));

    $stat = UserStat::where('user_id', $user->id)->first();
    expect($stat)->not->toBeNull()
        ->and($stat->total_points)->toBe(3)
        ->and($stat->predictions_made)->toBe(1);
});

it('accumulates points across multiple fixtures', function () {
    $user = User::factory()->create();

    $fixture1 = Fixture::factory()->completed()->create([
        'home_score' => 1,
        'away_score' => 0,
    ]);
    $fixture2 = Fixture::factory()->completed()->create([
        'home_score' => 2,
        'away_score' => 2,
    ]);

    Prediction::factory()->withPoints(3)->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture1->id,
    ]);

    Prediction::factory()->withPoints(3)->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture2->id,
        'home_score' => 2,
        'away_score' => 2,
    ]);

    event(new ResultImported($fixture2));

    $stat = UserStat::where('user_id', $user->id)->first();
    expect($stat)->not->toBeNull()
        ->and($stat->total_points)->toBe(6)
        ->and($stat->predictions_made)->toBe(2);
});

it('updates an existing user_stat row on re-import', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->completed()->create([
        'home_score' => 1,
        'away_score' => 0,
    ]);

    Prediction::factory()->withPoints(1)->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
        'home_score' => 2,
        'away_score' => 0,
    ]);

    UserStat::factory()->create([
        'user_id' => $user->id,
        'total_points' => 1,
        'predictions_made' => 1,
    ]);

    Prediction::where('user_id', $user->id)
        ->where('fixture_id', $fixture->id)
        ->update(['points' => 3, 'home_score' => 1, 'away_score' => 0]);

    event(new ResultImported($fixture));

    $stat = UserStat::where('user_id', $user->id)->first();
    expect($stat)->not->toBeNull()
        ->and($stat->total_points)->toBe(3)
        ->and($stat->predictions_made)->toBe(1);
});

it('only counts predictions with non-null points toward predictions_made', function () {
    $user = User::factory()->create();

    $scoredFixture = Fixture::factory()->completed()->create([
        'home_score' => 1,
        'away_score' => 0,
    ]);
    $unscoredFixture = Fixture::factory()->create();

    Prediction::factory()->withPoints(3)->create([
        'user_id' => $user->id,
        'fixture_id' => $scoredFixture->id,
    ]);

    Prediction::factory()->create([
        'user_id' => $user->id,
        'fixture_id' => $unscoredFixture->id,
        'points' => null,
    ]);

    event(new ResultImported($scoredFixture));

    $stat = UserStat::where('user_id', $user->id)->first();
    expect($stat)->not->toBeNull()
        ->and($stat->predictions_made)->toBe(1);
});
