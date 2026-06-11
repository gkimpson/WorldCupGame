<?php

use App\Enums\LeagueMemberRole;
use App\Livewire\League\MyLeagues;
use App\Livewire\League\ShowLeague;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use App\Models\UserStat;
use Livewire\Livewire;

it('requires authentication to view private leagues', function () {
    $this->get(route('leagues.index'))->assertRedirect(route('login'));
});

it('creates a private league and owner membership', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(MyLeagues::class)
        ->set('name', 'Office Sweepstake')
        ->call('createLeague')
        ->assertRedirect();

    $league = League::where('name', 'Office Sweepstake')->first();

    expect($league)->not->toBeNull()
        ->and($league->owner_user_id)->toBe($user->id)
        ->and(LeagueMember::where('league_id', $league->id)->where('user_id', $user->id)->first()->role)->toBe(LeagueMemberRole::Owner);
});

it('joins a league by code', function () {
    $owner = User::factory()->create();
    $league = League::factory()->create(['owner_user_id' => $owner->id]);
    LeagueMember::factory()->owner()->create([
        'league_id' => $league->id,
        'user_id' => $owner->id,
    ]);

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(MyLeagues::class)
        ->set('joinCode', strtolower($league->join_code))
        ->call('joinLeague')
        ->assertRedirect(route('leagues.show', $league, absolute: false));

    expect($league->hasMember($user))->toBeTrue();
});

it('shows a validation error for an unknown join code', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(MyLeagues::class)
        ->set('joinCode', 'UNKNOWN')
        ->call('joinLeague')
        ->assertHasErrors('joinCode');
});

it('blocks non-members from viewing a league', function () {
    $league = League::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('leagues.show', $league))
        ->assertForbidden();
});

it('shows members ordered by total points', function () {
    $league = League::factory()->create();
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    LeagueMember::factory()->create(['league_id' => $league->id, 'user_id' => $alice->id]);
    LeagueMember::factory()->create(['league_id' => $league->id, 'user_id' => $bob->id]);
    UserStat::factory()->withPoints(15)->create(['user_id' => $alice->id]);
    UserStat::factory()->withPoints(30)->create(['user_id' => $bob->id]);

    Livewire::actingAs($alice)
        ->test(ShowLeague::class, ['league' => $league])
        ->assertSeeInOrder(['Bob', 'Alice']);
});
