<?php

use App\Livewire\Leaderboard\GlobalLeaderboard;
use App\Models\User;
use App\Models\UserStat;
use Livewire\Livewire;

it('is publicly accessible without authentication', function () {
    $this->get(route('leaderboard.global'))->assertOk();
});

it('shows users ordered by total points descending', function () {
    $userA = User::factory()->create(['name' => 'Alice']);
    $userB = User::factory()->create(['name' => 'Bob']);

    UserStat::factory()->withPoints(30)->create(['user_id' => $userA->id]);
    UserStat::factory()->withPoints(20)->create(['user_id' => $userB->id]);

    Livewire::test(GlobalLeaderboard::class)
        ->assertSeeInOrder(['Alice', 'Bob']);
});

it('excludes dummy users', function () {
    $realUser = User::factory()->create(['name' => 'Real User']);
    $dummyUser = User::factory()->dummy()->create(['name' => 'Dummy User']);

    UserStat::factory()->withPoints(10)->create(['user_id' => $realUser->id]);
    UserStat::factory()->withPoints(999)->create(['user_id' => $dummyUser->id]);

    Livewire::test(GlobalLeaderboard::class)
        ->assertSee('Real User')
        ->assertDontSee('Dummy User');
});

it('assigns correct ranks starting at 1', function () {
    $user = User::factory()->create(['name' => 'Alice']);
    UserStat::factory()->withPoints(10)->create(['user_id' => $user->id]);

    Livewire::test(GlobalLeaderboard::class)
        ->assertViewHas('topEntries', fn ($entries) => $entries[0]['rank'] === 1);
});

it('caps the leaderboard at 100 entries', function () {
    User::factory()->count(101)->create()->each(function (User $user, int $i): void {
        UserStat::factory()->withPoints(200 - $i)->create(['user_id' => $user->id]);
    });

    Livewire::test(GlobalLeaderboard::class)
        ->assertViewHas('topEntries', fn ($entries) => count($entries) === 100);
});

it('shows no pinned entry for a guest', function () {
    Livewire::test(GlobalLeaderboard::class)
        ->assertViewHas('pinnedEntry', null);
});

it('shows no pinned entry when the authenticated user is in the top 100', function () {
    $me = User::factory()->create();
    UserStat::factory()->withPoints(50)->create(['user_id' => $me->id]);

    Livewire::actingAs($me)
        ->test(GlobalLeaderboard::class)
        ->assertViewHas('pinnedEntry', null);
});

it('pins the authenticated user when they are outside the top 100', function () {
    User::factory()->count(100)->create()->each(function (User $user, int $i): void {
        UserStat::factory()->withPoints(200 - $i)->create(['user_id' => $user->id]);
    });

    $me = User::factory()->create(['name' => 'Outsider']);
    UserStat::factory()->withPoints(1, 5)->create(['user_id' => $me->id]);

    Livewire::actingAs($me)
        ->test(GlobalLeaderboard::class)
        ->assertViewHas('pinnedEntry', fn ($entry) => $entry !== null
            && $entry['name'] === 'Outsider'
            && $entry['rank'] === 101
            && $entry['total_points'] === 1
            && $entry['predictions_made'] === 5);
});

it('breaks ties consistently by user_stat id', function () {
    $userA = User::factory()->create(['name' => 'Alice']);
    $userB = User::factory()->create(['name' => 'Bob']);

    UserStat::factory()->withPoints(10)->create(['user_id' => $userA->id]);
    UserStat::factory()->withPoints(10)->create(['user_id' => $userB->id]);

    Livewire::test(GlobalLeaderboard::class)
        ->assertSeeInOrder(['Alice', 'Bob'])
        ->assertViewHas('topEntries', fn ($entries) => $entries[0]['rank'] === 1
            && $entries[0]['name'] === 'Alice'
            && $entries[1]['rank'] === 2
            && $entries[1]['name'] === 'Bob');
});
