<?php

use App\Models\User;
use Filament\Panel;

it('casts the admin flag to a boolean', function () {
    $user = User::factory()->create([
        'is_admin' => 1,
    ]);

    expect($user->refresh()->is_admin)->toBeTrue();
});

it('allows admin flagged users to access the filament panel', function () {
    $user = User::factory()->create([
        'is_admin' => true,
    ]);

    expect($user->canAccessPanel(Panel::make()->id('admin')))->toBeTrue();
});

it('does not allow regular users to access the filament panel', function () {
    $user = User::factory()->create();

    expect($user->canAccessPanel(Panel::make()->id('admin')))->toBeFalse();
});
