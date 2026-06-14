<?php

namespace App\Services\Results;

use App\Models\Fixture;
use App\Services\Results\Contracts\WorldCupResultsProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class GeminiResultsService implements WorldCupResultsProviderInterface
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly ResultsResponseParser $parser,
    ) {}

    /**
     * @param  Collection<int, Fixture>  $fixtures
     * @return array<int, array{home_score: int|null, away_score: int|null, status: string}>
     */
    public function fetchResults(Collection $fixtures): array
    {
        $fixtureList = $fixtures->map(fn (Fixture $fixture) => [
            'id' => $fixture->id,
            'home' => ($ht = $fixture->homeTeam) !== null ? $ht->name : ($fixture->home_team_placeholder ?? 'TBD'),
            'away' => ($at = $fixture->awayTeam) !== null ? $at->name : ($fixture->away_team_placeholder ?? 'TBD'),
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
        $decoded = json_decode($this->parser->extractJson($text), true);

        if (! is_array($decoded)) {
            Log::warning('GeminiResultsService: could not parse JSON response from Gemini', ['response' => $text]);

            return [];
        }

        return $this->parser->normalise($decoded);
    }

    /** @throws RuntimeException */
    public function fetchRawResults(?string $specificDate = null): string
    {
        $response = Http::post(
            self::API_BASE."/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [['text' => $this->buildRawPrompt($specificDate)]],
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

    private function buildRawPrompt(?string $specificDate = null): string
    {
        $today = now()->format('l, F j, Y');

        if ($specificDate !== null) {
            $label = Carbon::parse($specificDate)->format('l, F j, Y');

            return "Today is {$today}. The FIFA World Cup 2026 is currently underway. "
                ."Use Google Search to find results ONLY for matches played on {$label} ({$specificDate}). "
                ."Search for \"FIFA World Cup 2026 results {$specificDate}\" and \"World Cup 2026 scores {$label}\". "
                .'List every match played on that date with the final score (home team, away team, home score – away score). '
                .'Do not include matches from any other date. '
                .'Do not say results are unavailable — search and report what you find.';
        }

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

        $aliasNote = $this->parser->buildAliasNote();

        return <<<PROMPT
        You are a sports data assistant. Search for FIFA World Cup 2026 match results.

        Below is a list of matches. For each match, search for the final score if the match has been played, or the current score if it is in progress.

        Matches:
        {$json}
        {$aliasNote}

        Return ONLY a JSON array for matches that are fully completed (final whistle blown, official result confirmed).
        Do NOT include matches that are in progress, scheduled but not yet started, or where you are uncertain of the final result.

        Example format:
        [
          {"id": "01JXXXXXXXXXXXXXXXXXXXXXXXXX", "home_score": 2, "away_score": 0, "status": "completed"}
        ]

        Rules:
        - Only include a match if status is "completed" and you have confirmed the final score.
        - Omit any match that has not yet finished or where the result is unknown.
        - Only set home_score and away_score to integers for completed matches.
        PROMPT;
    }
}
