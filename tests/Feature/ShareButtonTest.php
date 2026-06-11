<?php

use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\Team;
use App\Models\User;
use App\Models\UserStat;

// ---------------------------------------------------------------------------
// x-share-button component
// ---------------------------------------------------------------------------

it('renders share button with x-cloak and the given label', function () {
    $this->blade('<x-share-button title="Title" text="Body" url="https://example.com" label="Share this" />')
        ->assertSee('x-cloak', false)
        ->assertSee('data-testid="share-button"', false)
        ->assertSee('Share this');
});

it('defaults the button label to Share', function () {
    $this->blade('<x-share-button title="T" text="B" url="https://example.com" />')
        ->assertSee('>Share<', false);
});

it('embeds the share url in the component', function () {
    // @js() escapes forward slashes in URLs
    $this->blade('<x-share-button title="T" text="B" url="https://example.com/profile" />')
        ->assertSee('https:\/\/example.com\/profile', false);
});

// ---------------------------------------------------------------------------
// Profile page — Surface 1
// ---------------------------------------------------------------------------

it('shows the share button on your own profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('users.show', $user))
        ->assertSee('data-testid="share-button"', false);
});

it('does not show the share button when viewing another users profile', function () {
    $viewer = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('users.show', $other))
        ->assertDontSee('data-testid="share-button"', false);
});

it('includes rank in the profile share text when the user has a ranking', function () {
    $user = User::factory()->create();
    UserStat::factory()->create([
        'user_id' => $user->id,
        'total_points' => 120,
        'predictions_made' => 10,
        'correct_outcomes' => 7,
        'exact_scores' => 2,
    ]);

    $response = $this->actingAs($user)->get(route('users.show', $user));

    // @js() encodes apostrophes as ' — check for text without apostrophes
    $response->assertSee('ranked #1 with 120 pts', false);
});

it('omits rank from the profile share text when the user has no ranking', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('users.show', $user));

    // @js() encodes apostrophes as ' — check for text without apostrophes
    $response->assertSee('scored 0 pts at World Cup 104', false);
    $response->assertDontSee('ranked #', false);
});

// ---------------------------------------------------------------------------
// Fixture page — Surface 2
// ---------------------------------------------------------------------------

it('shows the share button on a fixture when the user has a scored prediction', function () {
    $user = User::factory()->create();
    $home = Team::factory()->create(['name' => 'Brazil', 'flag_code' => 'br']);
    $away = Team::factory()->create(['name' => 'Argentina', 'flag_code' => 'ar']);
    $fixture = Fixture::factory()->create([
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'status' => FixtureStatus::Completed,
        'home_score' => 2,
        'away_score' => 1,
    ]);
    Prediction::factory()->withPoints(3)->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
        'home_score' => 2,
        'away_score' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('fixtures.show', $fixture))
        ->assertSee('data-testid="share-button"', false);
});

it('does not show the share button on a fixture when the user has no prediction', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->completed()->create();

    $this->actingAs($user)
        ->get(route('fixtures.show', $fixture))
        ->assertDontSee('data-testid="share-button"', false);
});

it('does not show the share button on a fixture when the prediction has not been scored yet', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->create(['status' => FixtureStatus::Scheduled]);
    Prediction::factory()->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
        'points' => null,
    ]);

    $this->actingAs($user)
        ->get(route('fixtures.show', $fixture))
        ->assertDontSee('data-testid="share-button"', false);
});

// ---------------------------------------------------------------------------
// Global leaderboard — Surface 3
// ---------------------------------------------------------------------------

it('shows the share button on the pinned leaderboard row when the user is outside the top 100', function () {
    $topUsers = User::factory()->count(100)->create();
    foreach ($topUsers as $topUser) {
        UserStat::factory()->create(['user_id' => $topUser->id, 'total_points' => 200]);
    }

    $user = User::factory()->create();
    UserStat::factory()->create(['user_id' => $user->id, 'total_points' => 10]);

    $this->actingAs($user)
        ->get(route('leaderboard.global'))
        ->assertSee('data-testid="share-button"', false);
});

it('does not show the share button on the leaderboard when there is no pinned entry', function () {
    $user = User::factory()->create();
    UserStat::factory()->create(['user_id' => $user->id, 'total_points' => 50]);

    $this->actingAs($user)
        ->get(route('leaderboard.global'))
        ->assertDontSee('data-testid="share-button"', false);
});
