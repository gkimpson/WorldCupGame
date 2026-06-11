<?php

use App\Enums\LeagueMemberRole;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use Illuminate\Database\QueryException;

it('uses a ULID primary key', function () {
    $league = League::factory()->create();

    expect($league->id)->toBeString()
        ->and(strlen($league->id))->toBe(26);
});

it('creates a unique join code', function () {
    $league = League::factory()->create();

    expect($league->join_code)->toBeString()
        ->and(strlen($league->join_code))->toBe(8);
});

it('knows whether a user is a member', function () {
    $league = League::factory()->create();
    $member = User::factory()->create();
    $stranger = User::factory()->create();

    LeagueMember::factory()->create([
        'league_id' => $league->id,
        'user_id' => $member->id,
        'role' => LeagueMemberRole::Member,
    ]);

    expect($league->hasMember($member))->toBeTrue()
        ->and($league->hasMember($stranger))->toBeFalse();
});

it('enforces one membership per user per league', function () {
    $league = League::factory()->create();
    $user = User::factory()->create();

    LeagueMember::factory()->create([
        'league_id' => $league->id,
        'user_id' => $user->id,
    ]);

    expect(fn () => LeagueMember::factory()->create([
        'league_id' => $league->id,
        'user_id' => $user->id,
    ]))->toThrow(QueryException::class);
});
