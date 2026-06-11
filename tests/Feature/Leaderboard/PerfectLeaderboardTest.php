<?php

use App\Livewire\Leaderboard\PerfectLeaderboard;
use App\Models\User;
use App\Models\UserStat;
use Livewire\Livewire;

it('is publicly accessible without authentication', function () {
    Livewire::test(PerfectLeaderboard::class)
        ->assertStatus(200);
});

it('orders entries by exact_scores descending', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    UserStat::factory()->withAccuracy(10, 5, 20, 15)->create(['user_id' => $userA->id]);
    UserStat::factory()->withAccuracy(10, 2, 20, 10)->create(['user_id' => $userB->id]);

    $entries = Livewire::test(PerfectLeaderboard::class)->viewData('topEntries');
    expect($entries[0]['name'])->toBe($userA->name)
        ->and($entries[1]['name'])->toBe($userB->name);
});

it('breaks ties by total_points descending', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    // Same exact_scores, but userA has more total points
    UserStat::factory()->withAccuracy(10, 3, 20, 30)->create(['user_id' => $userA->id]);
    UserStat::factory()->withAccuracy(10, 3, 20, 20)->create(['user_id' => $userB->id]);

    $entries = Livewire::test(PerfectLeaderboard::class)->viewData('topEntries');
    expect($entries[0]['name'])->toBe($userA->name);
});

it('assigns ranks starting at 1', function () {
    UserStat::factory()->withAccuracy(10, 5, 20, 15)->create(['user_id' => User::factory()->create()->id]);

    $entries = Livewire::test(PerfectLeaderboard::class)->viewData('topEntries');
    expect($entries[0]['rank'])->toBe(1);
});

it('caps at 100 entries', function () {
    User::factory()->count(105)->create()->each(function (User $user) {
        UserStat::factory()->withAccuracy(fake()->numberBetween(1, 50), fake()->numberBetween(0, 5), 10, 5)->create(['user_id' => $user->id]);
    });

    $entries = Livewire::test(PerfectLeaderboard::class)->viewData('topEntries');
    expect(count($entries))->toBeLessThanOrEqual(100);
});

it('shows no pinned entry when authenticated user is in the top 100', function () {
    $user = User::factory()->create();
    UserStat::factory()->withAccuracy(10, 5, 20, 15)->create(['user_id' => $user->id]);

    $pinnedEntry = Livewire::actingAs($user)
        ->test(PerfectLeaderboard::class)
        ->viewData('pinnedEntry');

    expect($pinnedEntry)->toBeNull();
});

it('pins the authenticated user with correct rank when outside top 100', function () {
    // Create 100 users ahead
    User::factory()->count(100)->create()->each(function (User $user) {
        UserStat::factory()->withAccuracy(50, 10, 20, 30)->create(['user_id' => $user->id]);
    });

    $myUser = User::factory()->create();
    UserStat::factory()->withAccuracy(1, 0, 5, 1)->create(['user_id' => $myUser->id]);

    $pinnedEntry = Livewire::actingAs($myUser)
        ->test(PerfectLeaderboard::class)
        ->viewData('pinnedEntry');

    expect($pinnedEntry)->not->toBeNull()
        ->and($pinnedEntry['rank'])->toBeGreaterThan(100);
});
