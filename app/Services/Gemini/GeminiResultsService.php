<?php

namespace App\Services\Gemini;

use App\Models\Fixture;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class GeminiResultsService
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
    ) {}

    /**
     * Fetch World Cup results for the given fixtures from Gemini with Search Grounding.
     *
     * @param  Collection<int, Fixture>  $fixtures
     * @return array<int, array{home_score: int|null, away_score: int|null, status: string}>
     */
    public function fetchResults(Collection $fixtures): array
    {
        $fixtureList = $fixtures->map(fn (Fixture $fixture) => [
            'id' => $fixture->id,
            'home' => $fixture->homeTeam?->name ?? $fixture->home_team_placeholder ?? 'TBD',
            'away' => $fixture->awayTeam?->name ?? $fixture->away_team_placeholder ?? 'TBD',
            'date' => $fixture->scheduled_at?->format('Y-m-d') ?? '',
        ])->values()->all();

        $response = Http::post(
            self::API_BASE."/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [['text' => $this->buildPrompt($fixtureList)]],
                    ],
                ],
                'tools' => [['google_search' => (object) []]],
            ],
        );

        if ($response->failed()) {
            throw new RuntimeException("Gemini API request failed with status {$response->status()}: {$response->body()}");
        }

        $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
        $decoded = json_decode($this->extractJson($text), true);

        if (! is_array($decoded)) {
            Log::warning('GeminiResultsService: could not parse JSON response from Gemini', ['response' => $text]);

            return [];
        }

        return collect($decoded)
            ->keyBy('id')
            ->map(fn (array $r) => [
                'home_score' => isset($r['home_score']) && is_int($r['home_score']) ? $r['home_score'] : null,
                'away_score' => isset($r['away_score']) && is_int($r['away_score']) ? $r['away_score'] : null,
                'status' => (string) ($r['status'] ?? 'not_started'),
            ])
            ->all();
    }

    /**
     * Fetch raw World Cup results from Gemini without needing fixture data.
     * Returns the plain text response from Gemini for direct inspection.
     *
     * @throws RuntimeException
     */
    public function fetchRawResults(): string
    {
        $response = Http::post(
            self::API_BASE."/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [['text' => $this->buildRawPrompt()]],
                    ],
                ],
                'tools' => [['google_search' => (object) []]],
            ],
        );

        if ($response->failed()) {
            throw new RuntimeException("Gemini API request failed with status {$response->status()}: {$response->body()}");
        }

        return (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
    }

    /** Strip markdown code fences so json_decode can parse the response text cleanly. */
    private function extractJson(string $text): string
    {
        $stripped = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $stripped = preg_replace('/\s*```\s*$/m', '', (string) $stripped);

        return trim((string) $stripped);
    }

    private function buildRawPrompt(): string
    {
        $today = now()->format('l, F j, Y');

        return "Today is {$today}. The FIFA World Cup 2026 is currently underway. "
            .'Use Google Search to find the latest match results right now. '
            .'Search for "FIFA World Cup 2026 results today" and "World Cup 2026 scores". '
            .'List every match that has been played so far with the final score (home team, away team, home score – away score). '
            .'Include any match currently in progress with the live score. '
            .'Group results by matchday or round. Do not say results are unavailable — search and report what you find.';
    }

    /** @param array<int, array{id: int, home: string, away: string, date: string}> $fixtures */
    private function buildPrompt(array $fixtures): string
    {
        $json = json_encode($fixtures, JSON_PRETTY_PRINT);

        return <<<PROMPT
        You are a sports data assistant. Search for FIFA World Cup 2026 match results.

        Below is a list of matches. For each match, search for the final score if the match has been played, or the current score if it is in progress.

        Matches:
        {$json}

        Return ONLY a JSON array with one entry per match using the exact same id values provided:
        [
          {"id": 1, "home_score": 2, "away_score": 0, "status": "completed"},
          {"id": 2, "home_score": null, "away_score": null, "status": "not_started"}
        ]

        Status values:
        - "completed" — the match has finished (use the final score)
        - "in_progress" — the match is currently being played
        - "not_started" — the match has not yet been played or no result was found

        Only set home_score and away_score to integers when status is "completed" or "in_progress". Use null otherwise.
        PROMPT;
    }
}
