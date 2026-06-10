<?php

use App\Models\User;
use App\Models\UserStat;
use Illuminate\Database\QueryException;

it('uses a ULID primary key', function () {
    $stat = UserStat::factory()->create();

    expect($stat->id)->toBeString()
        ->and(strlen($stat->id))->toBe(26);
});

it('defaults to zero points and zero predictions', function () {
    $stat = UserStat::factory()->create();

    expect($stat->total_points)->toBe(0)
        ->and($stat->predictions_made)->toBe(0);
});

it('enforces one stat row per user', function () {
    $user = User::factory()->create();

    UserStat::factory()->create(['user_id' => $user->id]);

    expect(fn () => UserStat::factory()->create(['user_id' => $user->id]))
        ->toThrow(QueryException::class);
});
