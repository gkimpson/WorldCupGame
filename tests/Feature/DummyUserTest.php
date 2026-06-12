<?php

use App\Models\User;

it('does not hide dummy users from default auth model queries', function () {
    User::factory()->create(['name' => 'Real User']);
    User::factory()->dummy()->create(['name' => 'Dummy User']);

    $users = User::all();

    expect($users)->toHaveCount(2);
});

it('scopes queries to non-dummy users when requested', function () {
    User::factory()->create(['name' => 'Real User']);
    User::factory()->dummy()->create(['name' => 'Dummy User']);

    $users = User::notDummy()->get();

    expect($users)->toHaveCount(1)
        ->and($users->first()->name)->toBe('Real User');
});

it('sets is_dummy to true via the dummy factory state', function () {
    $user = User::factory()->dummy()->create();

    expect($user->refresh()->is_dummy)->toBeTrue();
});

it('sets is_dummy to false by default', function () {
    $user = User::factory()->create()->fresh();

    expect($user->is_dummy)->toBeFalse();
});
