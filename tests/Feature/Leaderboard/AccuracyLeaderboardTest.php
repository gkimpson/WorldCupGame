<?php

use App\Livewire\Leaderboard\AccuracyLeaderboard;
use App\Models\User;
use App\Models\UserStat;
use Livewire\Livewire;

it('is publicly accessible without authentication', function () {
    Livewire::test(AccuracyLeaderboard::class)
        ->assertStatus(200);
});

it('orders entries by correct_outcomes descending', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    UserStat::factory()->withAccuracy(10, 3, 20, 15)->create(['user_id' => $userA->id]);
    UserStat::factory()->withAccuracy(5, 1, 10, 6)->create(['user_id' => $userB->id]);

    $component = Livewire::test(AccuracyLeaderboard::class);

    $entries = $component->viewData('topEntries');
    expect($entries[0]['name'])->toBe($userA->name)
        ->and($entries[1]['name'])->toBe($userB->name);
});

it('breaks ties by predictions_made ascending', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    // Same correct_outcomes, but userA made fewer predictions (more impressive)
    UserStat::factory()->withAccuracy(5, 2, 8, 10)->create(['user_id' => $userA->id]);
    UserStat::factory()->withAccuracy(5, 2, 12, 10)->create(['user_id' => $userB->id]);

    $entries = Livewire::test(AccuracyLeaderboard::class)->viewData('topEntries');
    expect($entries[0]['name'])->toBe($userA->name);
});

it('assigns ranks starting at 1', function () {
    UserStat::factory()->withAccuracy(10, 3, 20, 15)->create(['user_id' => User::factory()->create()->id]);

    $entries = Livewire::test(AccuracyLeaderboard::class)->viewData('topEntries');
    expect($entries[0]['rank'])->toBe(1);
});

it('caps at 100 entries', function () {
    User::factory()->count(105)->create()->each(function (User $user) {
        UserStat::factory()->withAccuracy(fake()->numberBetween(1, 50), 0, 10, 5)->create(['user_id' => $user->id]);
    });

    $entries = Livewire::test(AccuracyLeaderboard::class)->viewData('topEntries');
    expect(count($entries))->toBeLessThanOrEqual(100);
});

it('shows no pinned entry when authenticated user is in the top 100', function () {
    $user = User::factory()->create();
    UserStat::factory()->withAccuracy(10, 3, 20, 15)->create(['user_id' => $user->id]);

    $pinnedEntry = Livewire::actingAs($user)
        ->test(AccuracyLeaderboard::class)
        ->viewData('pinnedEntry');

    expect($pinnedEntry)->toBeNull();
});

it('pins the authenticated user with correct rank when outside top 100', function () {
    // Create 100 users ahead of our user
    User::factory()->count(100)->create()->each(function (User $user) {
        UserStat::factory()->withAccuracy(50, 10, 20, 30)->create(['user_id' => $user->id]);
    });

    $myUser = User::factory()->create();
    UserStat::factory()->withAccuracy(1, 0, 5, 1)->create(['user_id' => $myUser->id]);

    $component = Livewire::actingAs($myUser)->test(AccuracyLeaderboard::class);
    $pinnedEntry = $component->viewData('pinnedEntry');

    expect($pinnedEntry)->not->toBeNull()
        ->and($pinnedEntry['rank'])->toBeGreaterThan(100);
});
