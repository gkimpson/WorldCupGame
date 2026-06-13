<?php

use App\Livewire\Leaderboard\BiggestMovers;
use App\Models\User;
use App\Models\UserWeeklyStat;
use Livewire\Livewire;

it('is publicly accessible without authentication', function () {
    $this->get(route('leaderboard.movers'))->assertOk();
});

it('shows users ordered by largest absolute rank change', function () {
    // Week 1: Alice rank 1 (30pts), Bob rank 2 (20pts), Carol rank 3 (10pts)
    // Week 2: Carol rank 1 (50pts), Bob rank 2 (20pts), Alice rank 3 (10pts)
    // Carol: prev_rank=3, current_rank=1, rank_change=+2 (biggest riser)
    // Alice: prev_rank=1, current_rank=3, rank_change=-2 (biggest faller)
    // Bob:   prev_rank=2, current_rank=2, rank_change=0

    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);
    $carol = User::factory()->create(['name' => 'Carol']);

    UserWeeklyStat::factory()->create(['user_id' => $alice->id, 'week_number' => 1, 'total_points' => 30]);
    UserWeeklyStat::factory()->create(['user_id' => $bob->id, 'week_number' => 1, 'total_points' => 20]);
    UserWeeklyStat::factory()->create(['user_id' => $carol->id, 'week_number' => 1, 'total_points' => 10]);

    UserWeeklyStat::factory()->create(['user_id' => $carol->id, 'week_number' => 2, 'total_points' => 50]);
    UserWeeklyStat::factory()->create(['user_id' => $bob->id, 'week_number' => 2, 'total_points' => 20]);
    UserWeeklyStat::factory()->create(['user_id' => $alice->id, 'week_number' => 2, 'total_points' => 10]);

    Livewire::test(BiggestMovers::class)
        ->assertSeeInOrder(['Carol', 'Alice', 'Bob']);
});

it('passes correct rank change data to the view', function () {
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    UserWeeklyStat::factory()->create(['user_id' => $alice->id, 'week_number' => 1, 'total_points' => 30]);
    UserWeeklyStat::factory()->create(['user_id' => $bob->id, 'week_number' => 1, 'total_points' => 10]);

    UserWeeklyStat::factory()->create(['user_id' => $bob->id, 'week_number' => 2, 'total_points' => 40]);
    UserWeeklyStat::factory()->create(['user_id' => $alice->id, 'week_number' => 2, 'total_points' => 10]);

    Livewire::test(BiggestMovers::class)
        ->assertViewHas('movers', function (array $movers): bool {
            // Bob moved from rank 2 to rank 1 (+1), Alice from rank 1 to rank 2 (-1)
            // Bob is first (positive rank_change beats equal negative)
            return $movers[0]->name === 'Bob'
                && (int) $movers[0]->rank_change === 1
                && (int) $movers[0]->current_rank === 1
                && (int) $movers[0]->prev_rank === 2;
        });
});

it('excludes dummy users', function () {
    $realUser = User::factory()->create(['name' => 'Real User']);
    $dummyUser = User::factory()->dummy()->create(['name' => 'Dummy Mover']);

    UserWeeklyStat::factory()->create(['user_id' => $realUser->id, 'week_number' => 1, 'total_points' => 10]);
    UserWeeklyStat::factory()->create(['user_id' => $dummyUser->id, 'week_number' => 1, 'total_points' => 5]);
    UserWeeklyStat::factory()->create(['user_id' => $realUser->id, 'week_number' => 2, 'total_points' => 5]);
    UserWeeklyStat::factory()->create(['user_id' => $dummyUser->id, 'week_number' => 2, 'total_points' => 50]);

    Livewire::test(BiggestMovers::class)
        ->assertSee('Real User')
        ->assertDontSee('Dummy Mover');
});

it('shows an empty state when there is only one week of data', function () {
    $user = User::factory()->create();
    UserWeeklyStat::factory()->create(['user_id' => $user->id, 'week_number' => 1, 'total_points' => 10]);

    Livewire::test(BiggestMovers::class)
        ->assertViewHas('movers', fn (array $movers) => count($movers) === 0);
});

it('only includes users with stats in both the current and previous week', function () {
    $oldUser = User::factory()->create(['name' => 'Old User']);
    $newUser = User::factory()->create(['name' => 'New User']);

    UserWeeklyStat::factory()->create(['user_id' => $oldUser->id, 'week_number' => 1, 'total_points' => 30]);
    UserWeeklyStat::factory()->create(['user_id' => $newUser->id, 'week_number' => 2, 'total_points' => 50]);

    Livewire::test(BiggestMovers::class)
        ->assertViewHas('movers', fn (array $movers) => count($movers) === 0);
});
