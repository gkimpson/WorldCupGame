<?php

use App\Enums\FixtureStatus;
use App\Livewire\Dashboard;
use App\Models\Fixture;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\Prediction;
use App\Models\User;
use App\Models\UserStat;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

it('shows the empty state when user has made no predictions', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertViewHas('hasAnyPredictions', false);
});

it('does not show the empty state when user has made predictions', function () {
    $user = User::factory()->create();
    UserStat::factory()->withPoints(10, 5)->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertViewHas('hasAnyPredictions', true);
});

it('shows correct global rank on the stat cards', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userC = User::factory()->create();

    UserStat::factory()->withPoints(50)->create(['user_id' => $userA->id]);
    UserStat::factory()->withPoints(30)->create(['user_id' => $userB->id]);
    UserStat::factory()->withPoints(10)->create(['user_id' => $userC->id]);

    Livewire::actingAs($userB)
        ->test(Dashboard::class)
        ->assertViewHas('globalRank', 2);
});

it('shows correct total points on the stat cards', function () {
    $user = User::factory()->create();
    UserStat::factory()->withPoints(42, 15)->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertViewHas('totalPoints', 42);
});

it('shows correct predictions made count on the stat cards', function () {
    $user = User::factory()->create();
    UserStat::factory()->withPoints(10, 27)->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertViewHas('predictionsMade', 27);
});

it('shows upcoming fixtures that are future and not completed', function () {
    $user = User::factory()->create();

    $upcoming = Fixture::factory()->create([
        'scheduled_at' => now()->addDays(1),
        'status' => FixtureStatus::Scheduled,
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertViewHas('upcomingFixtures', fn ($fixtures) => $fixtures->contains('id', $upcoming->id));
});

it('does not show completed fixtures in the upcoming section', function () {
    $user = User::factory()->create();

    $completed = Fixture::factory()->completed()->create([
        'scheduled_at' => now()->subDays(1),
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertViewHas('upcomingFixtures', fn ($fixtures) => ! $fixtures->contains('id', $completed->id));
});

it('limits upcoming fixtures to 5', function () {
    $user = User::factory()->create();

    Fixture::factory()->count(8)->create([
        'scheduled_at' => now()->addDays(1),
        'status' => FixtureStatus::Scheduled,
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertViewHas('upcomingFixtures', fn ($fixtures) => $fixtures->count() <= 5);
});

it('shows the last 5 scored predictions as recent results', function () {
    $user = User::factory()->create();

    $completedFixture = Fixture::factory()->completed()->create([
        'scheduled_at' => now()->subDays(1),
    ]);

    $prediction = Prediction::factory()->withPoints(3)->create([
        'user_id' => $user->id,
        'fixture_id' => $completedFixture->id,
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertViewHas('recentResults', fn ($results) => $results->contains('id', $prediction->id));
});

it('does not show unscored predictions in recent results', function () {
    $user = User::factory()->create();

    $completedFixture = Fixture::factory()->completed()->create([
        'scheduled_at' => now()->subDays(1),
    ]);

    $unscoredPrediction = Prediction::factory()->create([
        'user_id' => $user->id,
        'fixture_id' => $completedFixture->id,
        'points' => null,
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertViewHas('recentResults', fn ($results) => ! $results->contains('id', $unscoredPrediction->id));
});

it('limits recent results to 5', function () {
    $user = User::factory()->create();

    Fixture::factory()->completed()->count(8)->create([
        'scheduled_at' => now()->subDays(1),
    ])->each(function (Fixture $fixture) use ($user): void {
        Prediction::factory()->withPoints(1)->create([
            'user_id' => $user->id,
            'fixture_id' => $fixture->id,
        ]);
    });

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertViewHas('recentResults', fn ($results) => $results->count() === 5);
});

it('shows the league name and rank when user is in a league', function () {
    $user = User::factory()->create();
    UserStat::factory()->withPoints(10)->create(['user_id' => $user->id]);

    $league = League::factory()->create(['name' => 'The Lads']);
    LeagueMember::factory()->create(['league_id' => $league->id, 'user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertViewHas('topLeague', fn ($l) => $l !== null && $l->id === $league->id)
        ->assertViewHas('topLeagueRank', 1);
});

it('shows no league widget when user is not in any league', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertViewHas('topLeague', null)
        ->assertViewHas('topLeagueRank', null);
});
