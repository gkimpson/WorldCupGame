<?php

use App\Livewire\Users\PredictionHeatmap;
use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Fixture::factory()->count(104)->create();
});

it('match outcome grid contains all 104 fixtures', function () {
    $user = User::factory()->create();

    Livewire::test(PredictionHeatmap::class, ['user' => $user])
        ->tap(function ($component) {
            expect($component->viewData('outcomeGrid'))->toHaveCount(104);
        });
});

it('resolves exact result when points >= 3', function () {
    $user = User::factory()->create();
    $fixture = Fixture::first();

    Prediction::factory()->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
        'points' => 3,
    ]);

    Livewire::test(PredictionHeatmap::class, ['user' => $user])
        ->tap(function ($component) use ($fixture) {
            $row = $component->viewData('outcomeGrid')
                ->first(fn ($r) => $r['fixture']->id === $fixture->id);

            expect($row['result'])->toBe('exact');
        });
});

it('resolves correct result when points = 1', function () {
    $user = User::factory()->create();
    $fixture = Fixture::first();

    Prediction::factory()->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
        'points' => 1,
    ]);

    Livewire::test(PredictionHeatmap::class, ['user' => $user])
        ->tap(function ($component) use ($fixture) {
            $row = $component->viewData('outcomeGrid')
                ->first(fn ($r) => $r['fixture']->id === $fixture->id);

            expect($row['result'])->toBe('correct');
        });
});

it('resolves wrong result when points = 0', function () {
    $user = User::factory()->create();
    $fixture = Fixture::first();

    Prediction::factory()->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
        'points' => 0,
    ]);

    Livewire::test(PredictionHeatmap::class, ['user' => $user])
        ->tap(function ($component) use ($fixture) {
            $row = $component->viewData('outcomeGrid')
                ->first(fn ($r) => $r['fixture']->id === $fixture->id);

            expect($row['result'])->toBe('wrong');
        });
});

it('resolves none result for fixture with no prediction', function () {
    $user = User::factory()->create();
    $fixtureWithNoPrediction = Fixture::first();

    Livewire::test(PredictionHeatmap::class, ['user' => $user])
        ->tap(function ($component) use ($fixtureWithNoPrediction) {
            $row = $component->viewData('outcomeGrid')
                ->first(fn ($r) => $r['fixture']->id === $fixtureWithNoPrediction->id);

            expect($row['result'])->toBe('none');
        });
});

it('score grid dimensions reflect max scores with minimum 3x3', function () {
    $user = User::factory()->create();
    $fixtures = Fixture::take(3)->get();

    Prediction::factory()->create(['user_id' => $user->id, 'fixture_id' => $fixtures[0]->id, 'home_score' => 5, 'away_score' => 2, 'points' => 1]);
    Prediction::factory()->create(['user_id' => $user->id, 'fixture_id' => $fixtures[1]->id, 'home_score' => 1, 'away_score' => 4, 'points' => 0]);
    Prediction::factory()->create(['user_id' => $user->id, 'fixture_id' => $fixtures[2]->id, 'home_score' => 0, 'away_score' => 0, 'points' => 3]);

    Livewire::test(PredictionHeatmap::class, ['user' => $user])
        ->tap(function ($component) {
            $grid = $component->viewData('scoreGrid');

            expect($grid['maxHome'])->toBe(5);
            expect($grid['maxAway'])->toBe(4);
        });
});

it('score grid enforces minimum 3x3 when all scores are low', function () {
    $user = User::factory()->create();
    $fixture = Fixture::first();

    Prediction::factory()->create(['user_id' => $user->id, 'fixture_id' => $fixture->id, 'home_score' => 1, 'away_score' => 0, 'points' => 1]);

    Livewire::test(PredictionHeatmap::class, ['user' => $user])
        ->tap(function ($component) {
            $grid = $component->viewData('scoreGrid');

            expect($grid['maxHome'])->toBeGreaterThanOrEqual(3);
            expect($grid['maxAway'])->toBeGreaterThanOrEqual(3);
        });
});

it('score grid cell stores correct count, correct, and exact values', function () {
    $user = User::factory()->create();
    $fixtures = Fixture::take(3)->get();

    Prediction::factory()->create(['user_id' => $user->id, 'fixture_id' => $fixtures[0]->id, 'home_score' => 1, 'away_score' => 0, 'points' => 3]);
    Prediction::factory()->create(['user_id' => $user->id, 'fixture_id' => $fixtures[1]->id, 'home_score' => 1, 'away_score' => 0, 'points' => 1]);
    Prediction::factory()->create(['user_id' => $user->id, 'fixture_id' => $fixtures[2]->id, 'home_score' => 1, 'away_score' => 0, 'points' => 0]);

    Livewire::test(PredictionHeatmap::class, ['user' => $user])
        ->tap(function ($component) {
            $cell = $component->viewData('scoreGrid')['cells'][1][0];

            expect($cell['count'])->toBe(3);
            expect($cell['correct'])->toBe(2);
            expect($cell['exact'])->toBe(1);
        });
});

it('score grid is null for user with no scored predictions', function () {
    $user = User::factory()->create();

    Livewire::test(PredictionHeatmap::class, ['user' => $user])
        ->tap(function ($component) {
            expect($component->viewData('scoreGrid'))->toBeNull();
        });
});

it('compact mode passes null scoreGrid to view', function () {
    $user = User::factory()->create();
    $fixture = Fixture::first();

    Prediction::factory()->create(['user_id' => $user->id, 'fixture_id' => $fixture->id, 'home_score' => 1, 'away_score' => 0, 'points' => 3]);

    Livewire::test(PredictionHeatmap::class, ['user' => $user, 'compact' => true])
        ->tap(function ($component) {
            expect($component->viewData('scoreGrid'))->toBeNull();
        });
});

it('renders on the profile page', function () {
    $user = User::factory()->create();

    $this->get(route('users.show', $user))->assertOk()->assertSeeLivewire(PredictionHeatmap::class);
});

it('renders two compact heatmaps on compare page when both users are set', function () {
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    $this->get(route('users.compare.show', [$alice->id, $bob->id]))
        ->assertOk()
        ->assertSeeLivewire(PredictionHeatmap::class);
});
