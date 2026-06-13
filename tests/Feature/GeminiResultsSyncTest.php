<?php

use App\Enums\FixtureStatus;
use App\Events\ResultImported;
use App\Models\Fixture;
use App\Models\Team;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function geminiResponse(array $results): array
{
    return [
        'candidates' => [
            [
                'content' => [
                    'parts' => [
                        ['text' => json_encode($results)],
                    ],
                ],
            ],
        ],
    ];
}

function scheduledFixture(string $homeTeamName, string $awayTeamName, string $scheduledAt = '-1 hour'): Fixture
{
    $homeTeam = Team::factory()->create(['name' => $homeTeamName]);
    $awayTeam = Team::factory()->create(['name' => $awayTeamName]);

    return Fixture::factory()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'scheduled_at' => now()->modify($scheduledAt),
        'status' => FixtureStatus::Scheduled,
    ]);
}

it('updates a completed fixture with the score from Gemini', function () {
    Event::fake([ResultImported::class]);

    $fixture = scheduledFixture('Mexico', 'South Africa');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(geminiResponse([
            ['id' => $fixture->id, 'home_score' => 2, 'away_score' => 0, 'status' => 'completed'],
        ])),
    ]);

    $this->artisan('world-cup:sync-results-gemini')->assertExitCode(0);

    $fixture->refresh();
    expect($fixture->home_score)->toBe(2);
    expect($fixture->away_score)->toBe(0);
    expect($fixture->status)->toBe(FixtureStatus::Completed);
});

it('dispatches ResultImported event when a fixture is marked completed', function () {
    Event::fake([ResultImported::class]);

    $fixture = scheduledFixture('Argentina', 'Brazil');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(geminiResponse([
            ['id' => $fixture->id, 'home_score' => 1, 'away_score' => 1, 'status' => 'completed'],
        ])),
    ]);

    $this->artisan('world-cup:sync-results-gemini')->assertExitCode(0);

    Event::assertDispatched(ResultImported::class, fn ($event) => $event->fixture->id === $fixture->id);
});

it('does not update fixtures that are not yet started', function () {
    Event::fake([ResultImported::class]);

    $fixture = scheduledFixture('Germany', 'France');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(geminiResponse([
            ['id' => $fixture->id, 'home_score' => null, 'away_score' => null, 'status' => 'not_started'],
        ])),
    ]);

    $this->artisan('world-cup:sync-results-gemini')->assertExitCode(0);

    $fixture->refresh();
    expect($fixture->status)->toBe(FixtureStatus::Scheduled);
    expect($fixture->home_score)->toBeNull();
    Event::assertNotDispatched(ResultImported::class);
});

it('does not re-process already completed fixtures', function () {
    Event::fake([ResultImported::class]);

    $homeTeam = Team::factory()->create(['name' => 'Spain']);
    $awayTeam = Team::factory()->create(['name' => 'Portugal']);

    Fixture::factory()->completed()->create([
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'scheduled_at' => now()->subHours(2),
        'status' => FixtureStatus::Completed,
        'home_score' => 3,
        'away_score' => 2,
    ]);

    Http::fake();

    $this->artisan('world-cup:sync-results-gemini')->assertExitCode(0);

    // No Gemini call was made because there are no non-completed past fixtures
    Http::assertNothingSent();
    Event::assertNotDispatched(ResultImported::class);
});

it('does not update the database or dispatch events in dry-run mode', function () {
    Event::fake([ResultImported::class]);

    $fixture = scheduledFixture('England', 'Netherlands');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(geminiResponse([
            ['id' => $fixture->id, 'home_score' => 3, 'away_score' => 1, 'status' => 'completed'],
        ])),
    ]);

    $this->artisan('world-cup:sync-results-gemini', ['--dry-run' => true])->assertExitCode(0);

    $fixture->refresh();
    expect($fixture->status)->toBe(FixtureStatus::Scheduled);
    expect($fixture->home_score)->toBeNull();
    Event::assertNotDispatched(ResultImported::class);
});

it('skips fixtures with null scores even when status is completed', function () {
    Event::fake([ResultImported::class]);

    $fixture = scheduledFixture('Japan', 'South Korea');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(geminiResponse([
            ['id' => $fixture->id, 'home_score' => null, 'away_score' => null, 'status' => 'completed'],
        ])),
    ]);

    $this->artisan('world-cup:sync-results-gemini')->assertExitCode(0);

    $fixture->refresh();
    expect($fixture->status)->toBe(FixtureStatus::Scheduled);
    Event::assertNotDispatched(ResultImported::class);
});

it('handles malformed Gemini JSON gracefully without crashing', function () {
    Log::spy();

    $fixture = scheduledFixture('USA', 'Canada');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'not valid json {{{']]]],
            ],
        ]),
    ]);

    $this->artisan('world-cup:sync-results-gemini')->assertExitCode(0);

    $fixture->refresh();
    expect($fixture->status)->toBe(FixtureStatus::Scheduled);

    Log::shouldHaveReceived('warning')->once();
});

it('handles a Gemini API failure gracefully', function () {
    Log::spy();

    $fixture = scheduledFixture('Morocco', 'Senegal');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([], 500),
    ]);

    $this->artisan('world-cup:sync-results-gemini')->assertExitCode(1);

    $fixture->refresh();
    expect($fixture->status)->toBe(FixtureStatus::Scheduled);
});

it('shows raw Gemini data and fixture preview in dummy mode without updating the database', function () {
    Event::fake([ResultImported::class]);

    $fixture = scheduledFixture('England', 'Spain');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(geminiResponse([
            ['id' => $fixture->id, 'home_score' => 3, 'away_score' => 0, 'status' => 'completed'],
        ])),
    ]);

    $exitCode = Artisan::call('world-cup:sync-results-gemini', ['--dummy' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('England');
    expect($output)->toContain('Spain');
    expect($output)->toContain('completed');
    expect($output)->toContain('3');
    expect($output)->toContain('0');

    $fixture->refresh();
    expect($fixture->status)->toBe(FixtureStatus::Scheduled);
    expect($fixture->home_score)->toBeNull();
    Event::assertNotDispatched(ResultImported::class);
});

it('does not include future fixtures in the Gemini request', function () {
    Event::fake([ResultImported::class]);

    // Past fixture - should be included
    $pastFixture = scheduledFixture('Italy', 'Switzerland');

    // Future fixture - should NOT be included
    $futureTeamHome = Team::factory()->create(['name' => 'Belgium']);
    $futureTeamAway = Team::factory()->create(['name' => 'Denmark']);
    Fixture::factory()->create([
        'home_team_id' => $futureTeamHome->id,
        'away_team_id' => $futureTeamAway->id,
        'scheduled_at' => now()->addDays(2),
        'status' => FixtureStatus::Scheduled,
    ]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(geminiResponse([
            ['id' => $pastFixture->id, 'home_score' => 1, 'away_score' => 0, 'status' => 'completed'],
        ])),
    ]);

    $this->artisan('world-cup:sync-results-gemini')->assertExitCode(0);

    $sentRequest = Http::recorded()[0][0];
    $body = json_decode($sentRequest->body(), true);
    $prompt = data_get($body, 'contents.0.parts.0.text');

    expect($prompt)->toContain('Italy');
    expect($prompt)->not->toContain('Belgium');
});
