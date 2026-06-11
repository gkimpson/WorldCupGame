<?php

use App\Models\Fixture;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;

it('keeps app page wrappers aligned with the sidebar', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->create();
    $league = League::factory()->create(['owner_user_id' => $user->id]);

    LeagueMember::factory()->owner()->create([
        'league_id' => $league->id,
        'user_id' => $user->id,
    ]);

    $pages = [
        [route('fixtures.index'), 'class="flex w-full max-w-6xl flex-col gap-6"'],
        [route('fixtures.show', $fixture), 'class="flex w-full max-w-5xl flex-col gap-6"'],
        [route('predictions.index'), 'class="flex w-full max-w-5xl flex-col gap-6"'],
        [route('leagues.index'), 'class="flex w-full max-w-5xl flex-col gap-6"'],
        [route('leagues.show', $league), 'class="flex w-full max-w-5xl flex-col gap-6"'],
    ];

    foreach ($pages as [$url, $wrapperClass]) {
        $this->actingAs($user)
            ->get($url)
            ->assertSuccessful()
            ->assertSeeHtml($wrapperClass)
            ->assertDontSeeHtml('mx-auto flex w-full max-w')
            ->assertDontSeeHtml('flex w-full max-w-5xl flex-col gap-6 p-6')
            ->assertDontSeeHtml('flex w-full max-w-6xl flex-col gap-6 p-6');
    }
});
