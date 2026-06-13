<?php

use App\Models\Prediction;
use App\Models\User;
use App\Models\UserStat;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates user_stats rows from scored predictions', function () {
    $user = User::factory()->create(['is_dummy' => false]);

    Prediction::factory()->for($user)->withPoints(3)->create();
    Prediction::factory()->for($user)->withPoints(1)->create();
    Prediction::factory()->for($user)->withPoints(0)->create();

    $this->artisan('leaderboard:backfill-user-stats')->assertSuccessful();

    $stat = UserStat::where('user_id', $user->id)->first();

    expect($stat)->not->toBeNull()
        ->and($stat->total_points)->toBe(4)
        ->and($stat->predictions_made)->toBe(3)
        ->and($stat->correct_outcomes)->toBe(2)
        ->and($stat->exact_scores)->toBe(1);
});

it('skips users with no scored predictions', function () {
    $user = User::factory()->create(['is_dummy' => false]);
    Prediction::factory()->for($user)->create(); // points = null

    $this->artisan('leaderboard:backfill-user-stats')->assertSuccessful();

    expect(UserStat::where('user_id', $user->id)->exists())->toBeFalse();
});

it('skips dummy users', function () {
    $dummy = User::factory()->create(['is_dummy' => true]);
    Prediction::factory()->for($dummy)->withPoints(3)->create();

    $this->artisan('leaderboard:backfill-user-stats')->assertSuccessful();

    expect(UserStat::where('user_id', $dummy->id)->exists())->toBeFalse();
});

it('updates existing user_stats rows', function () {
    $user = User::factory()->create(['is_dummy' => false]);
    UserStat::factory()->for($user)->withPoints(99)->create();

    Prediction::factory()->for($user)->withPoints(3)->create();

    $this->artisan('leaderboard:backfill-user-stats')->assertSuccessful();

    expect(UserStat::where('user_id', $user->id)->value('total_points'))->toBe(3);
});
