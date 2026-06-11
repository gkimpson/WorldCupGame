<?php

use App\Livewire\Users\ShowProfile;
use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;
use App\Models\UserStat;
use Livewire\Livewire;

it('is publicly accessible without authentication', function () {
    $user = User::factory()->create();

    Livewire::test(ShowProfile::class, ['user' => $user])
        ->assertStatus(200);
});

it('shows the correct user name', function () {
    $user = User::factory()->create(['name' => 'Test Player']);
    UserStat::factory()->create(['user_id' => $user->id]);

    Livewire::test(ShowProfile::class, ['user' => $user])
        ->assertSee('Test Player');
});

it('shows the user total points from their UserStat', function () {
    $user = User::factory()->create();
    UserStat::factory()->withPoints(42)->create(['user_id' => $user->id]);

    Livewire::test(ShowProfile::class, ['user' => $user])
        ->assertSee('42');
});

it('shows the correct global rank', function () {
    $leader = User::factory()->create();
    UserStat::factory()->withPoints(100)->create(['user_id' => $leader->id]);

    $user = User::factory()->create();
    UserStat::factory()->withPoints(50)->create(['user_id' => $user->id]);

    $component = Livewire::test(ShowProfile::class, ['user' => $user]);
    // user is rank 2 (leader has more points)
    expect($component->get('globalRank'))->toBe(2);
});

it('shows accuracy percentage', function () {
    $user = User::factory()->create();
    UserStat::factory()->withAccuracy(8, 2, 10, 15)->create(['user_id' => $user->id]);

    $component = Livewire::test(ShowProfile::class, ['user' => $user]);
    // 8 correct out of 10 = 80%
    expect($component->get('accuracyPct'))->toBe(80.0);
});

it('shows recent scored predictions up to 10', function () {
    $user = User::factory()->create();

    $fixtures = Fixture::factory()->completed()->count(12)->create([
        'home_score' => 1,
        'away_score' => 0,
    ]);

    foreach ($fixtures as $fixture) {
        Prediction::factory()->withPoints(1)->create([
            'user_id' => $user->id,
            'fixture_id' => $fixture->id,
        ]);
    }

    $component = Livewire::test(ShowProfile::class, ['user' => $user]);
    expect($component->get('recentResults'))->toHaveCount(10);
});

it('returns 404 for a non-existent user', function () {
    $response = $this->get('/users/nonexistent-id');
    $response->assertStatus(404);
});
