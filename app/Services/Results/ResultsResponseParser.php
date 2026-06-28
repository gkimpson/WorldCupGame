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
     * Returns a prompt note listing known team name aliases so AI providers can
     * match DB canonical names to alternate spellings found in search results.
     */
    public function buildAliasNote(): string
    {
        return <<<'NOTE'

        Team name aliases — some teams appear under different names in news and search results.
        When searching for a match, treat these as the same team:
        - Turkey = Türkiye
        - Netherlands = Holland
        - Ivory Coast = Côte d'Ivoire / Cote d'Ivoire
        - Iran = IR Iran
        - South Korea = Korea Republic
        - Cape Verde = Cabo Verde
        - Czech Republic = Czechia
        - United States = USA / US
        - DR Congo = Democratic Republic of Congo / Congo DR
        - Bosnia and Herzegovina = Bosnia & Herzegovina / BiH
        NOTE;
    }

    /**
     * Map a raw decoded array to the canonical result shape, keyed by fixture id.
     *
     * @param  array<int, array<string, mixed>>  $decoded
     * @return array<int, array{home_score: int|null, away_score: int|null, home_score_aet: int|null, away_score_aet: int|null, home_score_pens: int|null, away_score_pens: int|null, status: string}>
     */
    public function normalise(array $decoded): array
    {
        return collect($decoded)
            ->keyBy('id')
            ->map(fn (array $r) => [
                'home_score' => isset($r['home_score']) && is_int($r['home_score']) ? $r['home_score'] : null,
                'away_score' => isset($r['away_score']) && is_int($r['away_score']) ? $r['away_score'] : null,
                'home_score_aet' => isset($r['home_score_aet']) && is_int($r['home_score_aet']) ? $r['home_score_aet'] : null,
                'away_score_aet' => isset($r['away_score_aet']) && is_int($r['away_score_aet']) ? $r['away_score_aet'] : null,
                'home_score_pens' => isset($r['home_score_pens']) && is_int($r['home_score_pens']) ? $r['home_score_pens'] : null,
                'away_score_pens' => isset($r['away_score_pens']) && is_int($r['away_score_pens']) ? $r['away_score_pens'] : null,
                'status' => (string) ($r['status'] ?? 'not_started'),
            ])
            ->all();
    }
}
