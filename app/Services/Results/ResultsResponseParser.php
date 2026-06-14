<?php

namespace App\Services\Results;

final class ResultsResponseParser
{
    /** Strip markdown code fences so json_decode can parse cleanly. */
    public function extractJson(string $text): string
    {
        $stripped = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $stripped = preg_replace('/\s*```\s*$/m', '', (string) $stripped);

        return trim((string) $stripped);
    }

    /**
     * Map a raw decoded array to the canonical result shape, keyed by fixture id.
     *
     * @param  array<int, array<string, mixed>>  $decoded
     * @return array<string, array{home_score: int|null, away_score: int|null, status: string}>
     */
    public function normalise(array $decoded): array
    {
        return collect($decoded)
            ->keyBy('id')
            ->map(fn (array $r) => [
                'home_score' => isset($r['home_score']) && is_int($r['home_score']) ? $r['home_score'] : null,
                'away_score' => isset($r['away_score']) && is_int($r['away_score']) ? $r['away_score'] : null,
                'status' => (string) ($r['status'] ?? 'not_started'),
            ])
            ->all();
    }
}
