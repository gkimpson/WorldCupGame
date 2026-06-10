<?php

use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;
use Illuminate\Database\QueryException;

it('uses a ULID primary key', function () {
    $prediction = Prediction::factory()->create();

    expect($prediction->id)->toBeString()
        ->and(strlen($prediction->id))->toBe(26);
});

it('has null points by default', function () {
    $prediction = Prediction::factory()->create();

    expect($prediction->points)->toBeNull();
});

it('enforces unique user and fixture combination', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->create();

    Prediction::factory()->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
    ]);

    expect(fn () => Prediction::factory()->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
    ]))->toThrow(QueryException::class);
});
