<?php

use App\Livewire\Users\CompareUsers;
use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;
use App\Models\UserStat;
use App\Models\UserWeeklyStat;
use Livewire\Livewire;

it('is publicly accessible without authentication', function () {
    $this->get(route('users.compare'))->assertOk();
});

it('renders both users names on the pre-filled url', function () {
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    $this->get(route('users.compare.show', [$alice->id, $bob->id]))
        ->assertOk()
        ->assertSee('Alice')
        ->assertSee('Bob');
});

it('passes correct points stat tiles for both users', function () {
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    UserStat::factory()->create([
        'user_id' => $alice->id,
        'total_points' => 120,
        'predictions_made' => 10,
        'correct_outcomes' => 8,
        'exact_scores' => 3,
    ]);

    UserStat::factory()->create([
        'user_id' => $bob->id,
        'total_points' => 80,
        'predictions_made' => 10,
        'correct_outcomes' => 5,
        'exact_scores' => 2,
    ]);

    Livewire::test(CompareUsers::class, ['userA' => $alice, 'userB' => $bob])
        ->assertSee('120')
        ->assertSee('80');
});

it('includes all week numbers from both users in allWeeks', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    UserWeeklyStat::factory()->create(['user_id' => $alice->id, 'week_number' => 1, 'total_points' => 10]);
    UserWeeklyStat::factory()->create(['user_id' => $alice->id, 'week_number' => 2, 'total_points' => 20]);
    UserWeeklyStat::factory()->create(['user_id' => $bob->id, 'week_number' => 2, 'total_points' => 15]);
    UserWeeklyStat::factory()->create(['user_id' => $bob->id, 'week_number' => 3, 'total_points' => 25]);

    $allWeeks = Livewire::test(CompareUsers::class, ['userA' => $alice, 'userB' => $bob])
        ->viewData('allWeeks');

    expect($allWeeks->values()->toArray())->toBe([1, 2, 3]);
});

it('only shows completed fixtures with at least one scored prediction in match list', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $completedFixture = Fixture::factory()->completed()->create([
        'home_score' => 2,
        'away_score' => 1,
    ]);
    $scheduledFixture = Fixture::factory()->create();

    Prediction::factory()->create([
        'user_id' => $alice->id,
        'fixture_id' => $completedFixture->id,
        'home_score' => 2,
        'away_score' => 0,
        'points' => 1,
    ]);

    Prediction::factory()->create([
        'user_id' => $alice->id,
        'fixture_id' => $scheduledFixture->id,
        'home_score' => 1,
        'away_score' => 0,
        'points' => null,
    ]);

    $matches = Livewire::test(CompareUsers::class, ['userA' => $alice, 'userB' => $bob])
        ->viewData('matches');

    expect($matches)->toHaveCount(1)
        ->and($matches[0]['fixture']->id)->toBe($completedFixture->id);
});

it('excludes dummy users from autocomplete search results', function () {
    User::factory()->create(['name' => 'Real Player', 'is_dummy' => false]);
    User::factory()->create(['name' => 'Dummy Player', 'is_dummy' => true]);

    $results = Livewire::test(CompareUsers::class)
        ->set('searchA', 'Player')
        ->viewData('searchResultsA');

    expect($results->pluck('name'))
        ->toContain('Real Player')
        ->not->toContain('Dummy Player');
});

it('returns 404 for an unknown user id in the url', function () {
    $alice = User::factory()->create();

    $this->get('/compare/'.$alice->id.'/999999999')
        ->assertNotFound();
});

it('shows compare with me button when logged in and viewing another users profile', function () {
    $viewer = User::factory()->create();
    $subject = User::factory()->create();

    $this->actingAs($viewer);

    $this->get(route('users.show', $subject))
        ->assertOk()
        ->assertSee('Compare with me');
});

it('hides compare with me button when viewing own profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get(route('users.show', $user))
        ->assertOk()
        ->assertDontSee('Compare with me');
});

it('hides compare with me button for guests', function () {
    $subject = User::factory()->create();

    $this->get(route('users.show', $subject))
        ->assertOk()
        ->assertDontSee('Compare with me');
});
