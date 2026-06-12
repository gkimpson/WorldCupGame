<?php

use App\Models\Scopes\ExcludeDummyUsersScope;
use App\Models\User;

it('excludes dummy users from default queries', function () {
    User::factory()->create(['name' => 'Real User']);
    User::factory()->dummy()->create(['name' => 'Dummy User']);

    $users = User::all();

    expect($users)->toHaveCount(1)
        ->and($users->first()->name)->toBe('Real User');
});

it('includes dummy users when scope is removed', function () {
    User::factory()->create(['name' => 'Real User']);
    User::factory()->dummy()->create(['name' => 'Dummy User']);

    $users = User::withoutGlobalScope(ExcludeDummyUsersScope::class)->get();

    expect($users)->toHaveCount(2);
});

it('sets is_dummy to true via the dummy factory state', function () {
    $user = User::factory()->dummy()->create();

    expect(
        User::withoutGlobalScope(ExcludeDummyUsersScope::class)
            ->find($user->id)
            ->is_dummy
    )->toBeTrue();
});

it('sets is_dummy to false by default', function () {
    $user = User::factory()->create()->fresh();

    expect($user->is_dummy)->toBeFalse();
});
