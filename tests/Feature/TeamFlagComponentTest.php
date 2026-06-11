<?php

use App\Models\Team;

it('renders a team flag from a blade flags code', function () {
    $team = Team::factory()->make([
        'name' => 'Brazil',
        'flag_code' => 'br',
    ]);

    $this->blade('<x-team-flag :team="$team" />', ['team' => $team])
        ->assertSee('Brazil')
        ->assertSee('<svg', false);
});

it('keeps a stable placeholder when a team has no flag code', function () {
    $team = Team::factory()->make([
        'name' => 'TBD',
        'flag_code' => null,
    ]);

    $this->blade('<x-team-flag :team="$team" />', ['team' => $team])
        ->assertDontSee('<svg', false)
        ->assertSee('inline-flex', false);
});
