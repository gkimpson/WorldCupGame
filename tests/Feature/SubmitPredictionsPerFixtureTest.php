<?php

use App\Livewire\Predictions\SubmitPredictions;
use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;
use Livewire\Livewire;

it('requires authentication to call saveFixture', function () {
    $fixture = Fixture::factory()->create(['scheduled_at' => now()->addDays(10)]);

    Livewire::test(SubmitPredictions::class)
        ->call('saveFixture', $fixture->id)
        ->assertForbidden();
});

it('saves a new prediction via saveFixture', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->create(['scheduled_at' => now()->addDays(10)]);

    Livewire::actingAs($user)
        ->test(SubmitPredictions::class)
        ->set("scores.{$fixture->id}.home", '3')
        ->set("scores.{$fixture->id}.away", '1')
        ->call('saveFixture', $fixture->id);

    $prediction = Prediction::where('user_id', $user->id)->where('fixture_id', $fixture->id)->first();

    expect($prediction)->not->toBeNull()
        ->and($prediction->home_score)->toBe(3)
        ->and($prediction->away_score)->toBe(1);
});

it('updates an existing prediction via saveFixture', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->create(['scheduled_at' => now()->addDays(10)]);
    $prediction = Prediction::factory()->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
        'home_score' => 0,
        'away_score' => 0,
    ]);

    Livewire::actingAs($user)
        ->test(SubmitPredictions::class)
        ->set("scores.{$fixture->id}.home", '2')
        ->set("scores.{$fixture->id}.away", '2')
        ->call('saveFixture', $fixture->id);

    expect($prediction->fresh()->home_score)->toBe(2)
        ->and($prediction->fresh()->away_score)->toBe(2);
});

it('marks the fixture as saved in savedFixtures after saveFixture', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->create(['scheduled_at' => now()->addDays(10)]);

    Livewire::actingAs($user)
        ->test(SubmitPredictions::class)
        ->set("scores.{$fixture->id}.home", '1')
        ->set("scores.{$fixture->id}.away", '0')
        ->call('saveFixture', $fixture->id)
        ->assertSet("savedFixtures.{$fixture->id}", true);
});

it('rejects saveFixture for a locked fixture', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->create(['scheduled_at' => now()->addHour()]); // within 2-hour lock window

    Livewire::actingAs($user)
        ->test(SubmitPredictions::class)
        ->set("scores.{$fixture->id}.home", '2')
        ->set("scores.{$fixture->id}.away", '1')
        ->call('saveFixture', $fixture->id)
        ->assertForbidden();
});

it('rejects saveFixture when fixture is admin-locked via is_locked column', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->create([
        'scheduled_at' => now()->addDays(10), // not time-locked
        'is_locked' => true,
    ]);

    Livewire::actingAs($user)
        ->test(SubmitPredictions::class)
        ->set("scores.{$fixture->id}.home", '2')
        ->set("scores.{$fixture->id}.away", '1')
        ->call('saveFixture', $fixture->id)
        ->assertForbidden();
});

it('skips admin-locked fixtures in bulk save', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->create([
        'scheduled_at' => now()->addDays(10),
        'is_locked' => true,
    ]);

    Livewire::actingAs($user)
        ->test(SubmitPredictions::class)
        ->set("scores.{$fixture->id}.home", '2')
        ->set("scores.{$fixture->id}.away", '1')
        ->call('save');

    expect(Prediction::where('user_id', $user->id)->where('fixture_id', $fixture->id)->exists())->toBeFalse();
});

it('rejects saveFixture for a completed fixture', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->completed()->create(['scheduled_at' => now()->subDay()]);

    Livewire::actingAs($user)
        ->test(SubmitPredictions::class)
        ->set("scores.{$fixture->id}.home", '2')
        ->set("scores.{$fixture->id}.away", '1')
        ->call('saveFixture', $fixture->id)
        ->assertForbidden();
});

it('validates scores in saveFixture', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->create(['scheduled_at' => now()->addDays(10)]);

    Livewire::actingAs($user)
        ->test(SubmitPredictions::class)
        ->set("scores.{$fixture->id}.home", '25')
        ->set("scores.{$fixture->id}.away", '0')
        ->call('saveFixture', $fixture->id)
        ->assertHasErrors(["scores.{$fixture->id}.home"]);
});
