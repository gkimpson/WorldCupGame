<?php

use App\Livewire\Dashboard;
use App\Models\User;
use Livewire\Livewire;

it('admin can impersonate a non-admin user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create(['is_admin' => false]);

    $this->actingAs($admin)
        ->get(route('impersonate', $target->id))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($target);
});

it('non-admin cannot impersonate another user', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $target = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('impersonate', $target->id))
        ->assertForbidden();
});

it('admin cannot impersonate another admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $otherAdmin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('impersonate', $otherAdmin->id));

    // Session should not have swapped — still authenticated as original admin
    $this->assertAuthenticatedAs($admin);
});

it('admin can leave impersonation and return to their own session', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $target = User::factory()->create(['is_admin' => false]);

    $this->actingAs($admin)
        ->get(route('impersonate', $target->id));

    $this->get(route('impersonate.leave'))
        ->assertRedirect();

    $this->assertAuthenticatedAs($admin);
});

it('dashboard shows user picker only for admins', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['is_admin' => false]);

    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertSet('impersonatableUsers', fn ($val) => $val !== null);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSet('impersonatableUsers', null);
});

it('dashboard user picker does not include admin users', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $otherAdmin = User::factory()->create(['is_admin' => true]);
    $regularUser = User::factory()->create(['is_admin' => false]);

    Livewire::actingAs($admin)
        ->test(Dashboard::class)
        ->assertSet('impersonatableUsers', function ($users) use ($otherAdmin, $regularUser) {
            return $users->contains('id', $regularUser->id)
                && ! $users->contains('id', $otherAdmin->id);
        });
});
