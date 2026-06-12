<?php

use App\Models\User;
use Filament\Facades\Filament;
use Laravel\Fortify\Features;
use Spatie\Permission\Models\Role;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('dummy admin users can authenticate for filament using the default eloquent provider', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $user = User::factory()
        ->dummy()
        ->create([
            'password' => 'password',
        ]);

    $user->assignRole('admin');

    expect(config('auth.providers.users.driver'))->toBe('eloquent');

    expect(auth()->attemptWhen(
        [
            'email' => $user->email,
            'password' => 'password',
        ],
        fn (User $user): bool => $user->canAccessPanel(Filament::getPanel('admin')),
    ))->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});
