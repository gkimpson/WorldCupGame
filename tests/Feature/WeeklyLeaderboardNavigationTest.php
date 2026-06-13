<?php

use App\Livewire\Leaderboard\GlobalLeaderboard;
use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;
use App\Models\UserWeeklyStat;
use Livewire\Livewire;

function seedWeeks(array $weekNumbers): User
{
    $user = User::factory()->create();

    foreach ($weekNumbers as $week) {
        $fixture = Fixture::factory()->create(['week_number' => $week]);
        Prediction::factory()->withPoints(1)->create(['user_id' => $user->id, 'fixture_id' => $fixture->id]);
        UserWeeklyStat::factory()->create(['user_id' => $user->id, 'week_number' => $week, 'total_points' => 1]);
    }

    return $user;
}

it('defaults to the current tournament week on mount', function () {
    seedWeeks([1, 2, 3]);

    // Freeze time in week 2 (2026-06-18 = day 7 → week 2)
    $this->travelTo(now()->parse('2026-06-18'));

    Livewire::test(GlobalLeaderboard::class)
        ->assertSet('week', 2);
});

it('falls back to the first available week when the current week has no data', function () {
    seedWeeks([2, 3]);

    // Week 1 has no data; current date is in week 1
    $this->travelTo(now()->parse('2026-06-13'));

    Livewire::test(GlobalLeaderboard::class)
        ->assertSet('week', 2);
});

it('navigates to the previous week', function () {
    seedWeeks([1, 2, 3]);

    Livewire::test(GlobalLeaderboard::class)
        ->set('week', 3)
        ->call('previousWeek')
        ->assertSet('week', 2);
});

it('navigates to the next week', function () {
    seedWeeks([1, 2, 3]);

    Livewire::test(GlobalLeaderboard::class)
        ->set('week', 2)
        ->call('nextWeek')
        ->assertSet('week', 3);
});

it('does not go below the first week', function () {
    seedWeeks([1]);

    Livewire::test(GlobalLeaderboard::class)
        ->assertSet('week', 1)
        ->call('previousWeek')
        ->assertSet('week', 1);
});

it('does not go above the last week', function () {
    seedWeeks([1]);

    Livewire::test(GlobalLeaderboard::class)
        ->assertSet('week', 1)
        ->call('nextWeek')
        ->assertSet('week', 1);
});

it('resets to all time', function () {
    seedWeeks([1]);

    Livewire::test(GlobalLeaderboard::class)
        ->assertSet('week', 1)
        ->call('showAllTime')
        ->assertSet('week', null);
});
