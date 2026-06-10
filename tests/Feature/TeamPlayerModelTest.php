<?php

use App\Enums\PlayerPosition;
use App\Models\Player;
use App\Models\Team;

it('has many players', function () {
    $team = Team::factory()->has(Player::factory()->count(3))->create();

    expect($team->players)->toHaveCount(3)
        ->and($team->players->first())->toBeInstanceOf(Player::class);
});

it('belongs to a team', function () {
    $player = Player::factory()->create();

    expect($player->team)->toBeInstanceOf(Team::class);
});

it('casts the position attribute to the PlayerPosition enum', function () {
    $player = Player::factory()->goalkeeper()->create();

    expect($player->position)->toBe(PlayerPosition::Goalkeeper);
});

it('deletes players when their team is deleted', function () {
    $team = Team::factory()->has(Player::factory()->count(2))->create();

    $team->delete();

    expect(Player::count())->toBe(0);
});
