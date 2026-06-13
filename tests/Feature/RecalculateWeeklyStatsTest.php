<?php

use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;
use App\Models\UserWeeklyStat;

it('populates user_weekly_stats from scored predictions', function () {
    $user = User::factory()->create();

    $week1Fixture = Fixture::factory()->create(['week_number' => 1]);
    $week2Fixture = Fixture::factory()->create(['week_number' => 2]);

    Prediction::factory()->withPoints(3)->create(['user_id' => $user->id, 'fixture_id' => $week1Fixture->id]);
    Prediction::factory()->withPoints(1)->create(['user_id' => $user->id, 'fixture_id' => $week2Fixture->id]);

    $this->artisan('leaderboard:recalculate-weekly-stats')->assertSuccessful();

    $week1Stat = UserWeeklyStat::where('user_id', $user->id)->where('week_number', 1)->first();
    $week2Stat = UserWeeklyStat::where('user_id', $user->id)->where('week_number', 2)->first();

    expect($week1Stat)->not->toBeNull()
        ->and($week1Stat->total_points)->toBe(3)
        ->and($week1Stat->predictions_made)->toBe(1)
        ->and($week1Stat->exact_scores)->toBe(1);

    expect($week2Stat)->not->toBeNull()
        ->and($week2Stat->total_points)->toBe(1)
        ->and($week2Stat->predictions_made)->toBe(1)
        ->and($week2Stat->correct_outcomes)->toBe(1);
});

it('skips users with no scored predictions in a week', function () {
    $user = User::factory()->create();
    Fixture::factory()->create(['week_number' => 1]);

    $this->artisan('leaderboard:recalculate-weekly-stats')->assertSuccessful();

    expect(UserWeeklyStat::where('user_id', $user->id)->count())->toBe(0);
});

it('excludes dummy users', function () {
    $dummy = User::factory()->create(['is_dummy' => true]);
    $fixture = Fixture::factory()->create(['week_number' => 1]);
    Prediction::factory()->withPoints(3)->create(['user_id' => $dummy->id, 'fixture_id' => $fixture->id]);

    $this->artisan('leaderboard:recalculate-weekly-stats')->assertSuccessful();

    expect(UserWeeklyStat::where('user_id', $dummy->id)->count())->toBe(0);
});

it('is idempotent when run multiple times', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->create(['week_number' => 1]);
    Prediction::factory()->withPoints(3)->create(['user_id' => $user->id, 'fixture_id' => $fixture->id]);

    $this->artisan('leaderboard:recalculate-weekly-stats')->assertSuccessful();
    $this->artisan('leaderboard:recalculate-weekly-stats')->assertSuccessful();

    expect(UserWeeklyStat::where('user_id', $user->id)->where('week_number', 1)->count())->toBe(1);
});
