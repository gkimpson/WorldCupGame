<?php

use App\Enums\FixtureStatus;
use App\Events\ResultImported;
use App\Models\Fixture;
use App\Models\Team;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function openAiResponse(array $results): array
{
    return [
        'output' => [
            [
                'type' => 'message',
                'content' => [
                    ['type' => 'output_text', 'text' => json_encode($results)],
                ],
            ],
        ],
    ];
}

function openAiFixture(string $homeTeamName, string $awayTeamName, string $scheduledAt = '-1 hour'): Fixture
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

it('updates a completed fixture with the score from OpenAI', function () {
    Event::fake([ResultImported::class]);

    $fixture = openAiFixture('Mexico', 'South Africa');

    Http::fake([
        'api.openai.com/*' => Http::response(openAiResponse([
            ['id' => $fixture->id, 'home_score' => 2, 'away_score' => 0, 'status' => 'completed'],
        ])),
    ]);

    $this->artisan('world-cup:sync-results', ['--provider' => 'openai'])->assertExitCode(0);

    $fixture->refresh();
    expect($fixture->home_score)->toBe(2);
    expect($fixture->away_score)->toBe(0);
    expect($fixture->status)->toBe(FixtureStatus::Completed);
});

it('dispatches ResultImported event when a fixture is marked completed via OpenAI', function () {
    Event::fake([ResultImported::class]);

    $fixture = openAiFixture('Argentina', 'Brazil');

    Http::fake([
        'api.openai.com/*' => Http::response(openAiResponse([
            ['id' => $fixture->id, 'home_score' => 1, 'away_score' => 1, 'status' => 'completed'],
        ])),
    ]);

    $this->artisan('world-cup:sync-results', ['--provider' => 'openai'])->assertExitCode(0);

    Event::assertDispatched(ResultImported::class, fn ($event) => $event->fixture->id === $fixture->id);
});

it('does not update the database or dispatch events in dry-run mode', function () {
    Event::fake([ResultImported::class]);

    $fixture = openAiFixture('England', 'Netherlands');

    Http::fake([
        'api.openai.com/*' => Http::response(openAiResponse([
            ['id' => $fixture->id, 'home_score' => 3, 'away_score' => 1, 'status' => 'completed'],
        ])),
    ]);

    $this->artisan('world-cup:sync-results', ['--provider' => 'openai', '--dry-run' => true])->assertExitCode(0);

    $fixture->refresh();
    expect($fixture->status)->toBe(FixtureStatus::Scheduled);
    Event::assertNotDispatched(ResultImported::class);
});

it('handles malformed OpenAI JSON gracefully without crashing', function () {
    Log::spy();

    $fixture = openAiFixture('USA', 'Canada');

    $malformedContent = 'not valid json ';

    Http::fake([
        'api.openai.com/*' => Http::response([
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        ['type' => 'output_text', 'text' => $malformedContent],
                    ],
                ],
            ],
        ]),
    ]);

    $this->artisan('world-cup:sync-results', ['--provider' => 'openai'])->assertExitCode(0);

    $fixture->refresh();
    expect($fixture->status)->toBe(FixtureStatus::Scheduled);
    Log::shouldHaveReceived('warning')->once();
});

it('handles an OpenAI API failure gracefully', function () {
    $fixture = openAiFixture('Morocco', 'Senegal');

    Http::fake([
        'api.openai.com/*' => Http::response([], 500),
    ]);

    $this->artisan('world-cup:sync-results', ['--provider' => 'openai'])->assertExitCode(1);

    $fixture->refresh();
    expect($fixture->status)->toBe(FixtureStatus::Scheduled);
});

it('shows raw OpenAI data in dummy mode without updating the database', function () {
    Event::fake([ResultImported::class]);

    $fixture = openAiFixture('England', 'Spain');

    Http::fake([
        'api.openai.com/*' => Http::response(openAiResponse([
            ['id' => $fixture->id, 'home_score' => 3, 'away_score' => 0, 'status' => 'completed'],
        ])),
    ]);

    $exitCode = Artisan::call('world-cup:sync-results', ['--provider' => 'openai', '--dummy' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('England');
    expect($output)->toContain('Spain');
    expect($output)->toContain('3');

    $fixture->refresh();
    expect($fixture->status)->toBe(FixtureStatus::Scheduled);
    Event::assertNotDispatched(ResultImported::class);
});
