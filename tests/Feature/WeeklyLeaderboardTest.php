<?php

use App\Events\ResultImported;
use App\Livewire\Leaderboard\GlobalLeaderboard;
use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;
use App\Models\UserStat;
use App\Models\UserWeeklyStat;
use Livewire\Livewire;

// --- RecalculateUserStats creates weekly stats ---

it('creates a user_weekly_stat row when a result is imported', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->completed()->create([
        'week_number' => 1,
        'home_score' => 2,
        'away_score' => 1,
    ]);

    Prediction::factory()->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
        'home_score' => 2,
        'away_score' => 1,
    ]);

    event(new ResultImported($fixture));

    $stat = UserWeeklyStat::where('user_id', $user->id)->where('week_number', 1)->first();
    expect($stat)->not->toBeNull()
        ->and($stat->total_points)->toBe(3)
        ->and($stat->predictions_made)->toBe(1);
});

it('accumulates weekly points only from fixtures in that week', function () {
    $user = User::factory()->create();

    $fixtureWeek1 = Fixture::factory()->completed()->create([
        'week_number' => 1,
        'home_score' => 2,
        'away_score' => 1,
    ]);
    $fixtureWeek2 = Fixture::factory()->completed()->create([
        'week_number' => 2,
        'home_score' => 0,
        'away_score' => 0,
    ]);

    // Exact score match in week 1 → 3 pts
    Prediction::factory()->create([
        'user_id' => $user->id,
        'fixture_id' => $fixtureWeek1->id,
        'home_score' => 2,
        'away_score' => 1,
    ]);
    // Correct outcome (draw) in week 2 → 1 pt
    Prediction::factory()->create([
        'user_id' => $user->id,
        'fixture_id' => $fixtureWeek2->id,
        'home_score' => 1,
        'away_score' => 1,
    ]);

    event(new ResultImported($fixtureWeek1));
    event(new ResultImported($fixtureWeek2));

    $week1 = UserWeeklyStat::where('user_id', $user->id)->where('week_number', 1)->first();
    $week2 = UserWeeklyStat::where('user_id', $user->id)->where('week_number', 2)->first();

    expect($week1->total_points)->toBe(3)
        ->and($week2->total_points)->toBe(1);
});

it('updates an existing weekly stat row on re-import', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->completed()->create([
        'week_number' => 1,
        'home_score' => 2,
        'away_score' => 1,
    ]);

    // Initially wrong prediction (correct outcome only → 1 pt after scoring)
    Prediction::factory()->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
        'home_score' => 1,
        'away_score' => 0,
    ]);

    event(new ResultImported($fixture));
    $statAfterFirst = UserWeeklyStat::where('user_id', $user->id)->where('week_number', 1)->first();
    expect($statAfterFirst->total_points)->toBe(1);

    // Correct the prediction to exact match → 3 pts
    Prediction::where('user_id', $user->id)->where('fixture_id', $fixture->id)
        ->update(['home_score' => 2, 'away_score' => 1]);

    event(new ResultImported($fixture));

    $stat = UserWeeklyStat::where('user_id', $user->id)->where('week_number', 1)->first();
    expect($stat->total_points)->toBe(3);
});

it('does not create a weekly stat when the fixture has no week_number', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->completed()->create([
        'week_number' => null,
        'home_score' => 1,
        'away_score' => 0,
    ]);

    Prediction::factory()->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
        'home_score' => 1,
        'away_score' => 0,
    ]);

    event(new ResultImported($fixture));

    expect(UserWeeklyStat::where('user_id', $user->id)->count())->toBe(0);
});

// --- Global leaderboard week filter ---

it('shows the overall leaderboard when no week is selected', function () {
    $userA = User::factory()->create(['name' => 'Alice']);
    $userB = User::factory()->create(['name' => 'Bob']);

    UserStat::factory()->withPoints(30)->create(['user_id' => $userA->id]);
    UserStat::factory()->withPoints(20)->create(['user_id' => $userB->id]);

    Livewire::test(GlobalLeaderboard::class)
        ->assertSeeInOrder(['Alice', 'Bob']);
});

it('filters the global leaderboard to a specific week', function () {
    $userA = User::factory()->create(['name' => 'Alice']);
    $userB = User::factory()->create(['name' => 'Bob']);

    // Overall Alice leads, but in week 2 Bob leads
    UserStat::factory()->withPoints(30)->create(['user_id' => $userA->id]);
    UserStat::factory()->withPoints(20)->create(['user_id' => $userB->id]);

    UserWeeklyStat::factory()->create(['user_id' => $userA->id, 'week_number' => 2, 'total_points' => 5]);
    UserWeeklyStat::factory()->create(['user_id' => $userB->id, 'week_number' => 2, 'total_points' => 15]);

    Livewire::test(GlobalLeaderboard::class)
        ->set('week', 2)
        ->assertSeeInOrder(['Bob', 'Alice']);
});

it('returns available weeks that have scored predictions', function () {
    $user = User::factory()->create();

    $fixtureWeek1 = Fixture::factory()->completed()->create([
        'week_number' => 1, 'home_score' => 1, 'away_score' => 0,
    ]);
    $fixtureWeek3 = Fixture::factory()->completed()->create([
        'week_number' => 3, 'home_score' => 0, 'away_score' => 0,
    ]);

    Prediction::factory()->create([
        'user_id' => $user->id,
        'fixture_id' => $fixtureWeek1->id,
        'home_score' => 1,
        'away_score' => 0,
    ]);
    Prediction::factory()->create([
        'user_id' => $user->id,
        'fixture_id' => $fixtureWeek3->id,
        'home_score' => 0,
        'away_score' => 0,
    ]);

    event(new ResultImported($fixtureWeek1));
    event(new ResultImported($fixtureWeek3));

    $weeks = Livewire::test(GlobalLeaderboard::class)->viewData('availableWeeks');

    expect($weeks->values()->all())->toBe([1, 3]);
});

it('shows no week tabs when no predictions have been scored yet', function () {
    Livewire::test(GlobalLeaderboard::class)
        ->assertViewHas('availableWeeks', fn ($weeks) => $weeks->isEmpty());
});
