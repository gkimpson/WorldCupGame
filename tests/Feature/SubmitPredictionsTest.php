<?php

use App\Livewire\Predictions\SubmitPredictions;
use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;
use Livewire\Livewire;

it('requires authentication to view predictions', function () {
    $this->get(route('predictions.index'))->assertRedirect(route('login'));
});

it('pre-fills existing predictions on mount', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->create(['scheduled_at' => now()->addDays(10)]);
    Prediction::factory()->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
        'home_score' => 2,
        'away_score' => 1,
    ]);

    Livewire::actingAs($user)
        ->test(SubmitPredictions::class)
        ->assertSet("scores.{$fixture->id}.home", '2')
        ->assertSet("scores.{$fixture->id}.away", '1');
});

it('saves a new prediction for an unlocked fixture', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->create(['scheduled_at' => now()->addDays(10)]);

    Livewire::actingAs($user)
        ->test(SubmitPredictions::class)
        ->set("scores.{$fixture->id}.home", '2')
        ->set("scores.{$fixture->id}.away", '0')
        ->call('save');

    $prediction = Prediction::where('user_id', $user->id)->where('fixture_id', $fixture->id)->first();

    expect($prediction)->not->toBeNull()
        ->and($prediction->home_score)->toBe(2)
        ->and($prediction->away_score)->toBe(0);
});

it('updates an existing prediction for an unlocked fixture', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->create(['scheduled_at' => now()->addDays(10)]);
    $prediction = Prediction::factory()->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
        'home_score' => 1,
        'away_score' => 1,
    ]);

    Livewire::actingAs($user)
        ->test(SubmitPredictions::class)
        ->set("scores.{$fixture->id}.home", '3')
        ->set("scores.{$fixture->id}.away", '2')
        ->call('save');

    expect($prediction->fresh()->home_score)->toBe(3)
        ->and($prediction->fresh()->away_score)->toBe(2);
});

it('keeps the default nil nil prediction for locked fixtures', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->create(['scheduled_at' => now()->addHour()]); // within 2-hour lock window

    Livewire::actingAs($user)
        ->test(SubmitPredictions::class)
        ->set("scores.{$fixture->id}.home", '2')
        ->set("scores.{$fixture->id}.away", '1')
        ->call('save');

    $prediction = Prediction::where('user_id', $user->id)->where('fixture_id', $fixture->id)->first();

    expect($prediction)->not->toBeNull()
        ->and($prediction->home_score)->toBe(0)
        ->and($prediction->away_score)->toBe(0);
});

it('validates that scores are non-negative integers', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->create(['scheduled_at' => now()->addDays(10)]);

    Livewire::actingAs($user)
        ->test(SubmitPredictions::class)
        ->set("scores.{$fixture->id}.home", '-1')
        ->set("scores.{$fixture->id}.away", '0')
        ->call('save')
        ->assertHasErrors(["scores.{$fixture->id}.home"]);
});
