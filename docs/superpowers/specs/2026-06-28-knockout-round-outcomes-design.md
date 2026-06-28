# Knockout Round Outcome Predictions — Design Spec

**Date:** 2026-06-28
**Status:** Approved

## Context

The tournament has reached knockout rounds where matches can end in a draw after 90 minutes, then go to After Extra Time (AET) or a penalty shootout. The current prediction system only stores a 90-minute scoreline and has no concept of how a match is decided. The scoring engine is partially knockout-aware on the result side (it already reads `home_score_aet` / `home_score_pens` from the fixture) but predictions and the import pipeline are not. This spec adds the full chain: prediction input, scoring, and result ingestion.

---

## Approach: KnockoutOutcome enum on Prediction

Add a nullable `knockout_outcome` column to `predictions`. A new `KnockoutOutcome` enum captures every possible knockout resolution. Group stage predictions are untouched (column stays null, scorer skips the check).

---

## Data Model

### New enum: `app/Enums/KnockoutOutcome.php`

```php
enum KnockoutOutcome: string
{
    case HomeWin     = 'home_win';
    case AwayWin     = 'away_win';
    case HomeWinAet  = 'home_win_aet';
    case AwayWinAet  = 'away_win_aet';
    case HomeWinPens = 'home_win_pens';
    case AwayWinPens = 'away_win_pens';
}
```

Helper methods:
- `winner(): string` — returns `'home'` or `'away'`
- `method(): string` — returns `'normal'`, `'aet'`, or `'pens'`
- `fromFixture(Fixture $fixture): self` — resolves from `home_score`, `away_score`, `home_score_aet`, `away_score_aet`, `home_score_pens`, `away_score_pens`
- `isDrawAt90(): bool` — true when `home_score === away_score`

### Migration: `predictions` table

Add one nullable column:

```php
$table->string('knockout_outcome')->nullable()->after('away_score');
```

### `Prediction` model

- Add `knockout_outcome` to fillable
- Cast to `KnockoutOutcome::class`

### Business rules enforced at validation

- For a **group stage** fixture (`!fixture->stage->isKnockout()`): `knockout_outcome` must be null.
- For a **knockout** fixture where the predicted score is **not a draw**: `knockout_outcome` must be `HomeWin` or `AwayWin` (inferred from the score — UI sets this automatically).
- For a **knockout** fixture where the predicted score **is a draw**: `knockout_outcome` must be one of `HomeWinAet`, `AwayWinAet`, `HomeWinPens`, `AwayWinPens`.

---

## Scoring Logic

### Points structure for knockout matches

| Condition | Points |
|---|---|
| Exact 90-min score (predicted score = actual 90-min score) | 3 pts |
| Correct winner (predicted winner = actual winner) | 1 pt |
| Correct method (normal / AET / pens) | +1 pt |

These stack. Maximum knockout score: **5 pts** (exact score + right winner + right method). Group stage max is unchanged at 3 pts.

Exact score check still uses the 90-min score (`home_score` / `away_score` on the fixture), not the AET or pens score.

### Changes to `app/Services/Scoring/FixturePredictionScorer.php`

1. Add `resolveActualKnockoutOutcome(Fixture $fixture): ?KnockoutOutcome` — returns null for group stage, otherwise uses `KnockoutOutcome::fromFixture($fixture)`.
2. Extend the `score()` method: after the existing exact/outcome point logic, if the fixture is a knockout stage and both the prediction and fixture have a settled knockout outcome, award +1 pt when `prediction->knockout_outcome->method() === actualOutcome->method()`.
3. The existing `settledHomeScore()` / `settledAwayScore()` AET fallback already handles the exact-score side correctly — no change needed there.

### Changes to `app/Services/Scoring/FixturePredictionScore.php`

Add `knockoutMethodCorrect: bool` to the value object so it can be displayed in UI and logged.

### `app/Listeners/RecalculateUserStats.php`

The correct-outcome threshold remains `points >= 1`. No change required — the new method point adds on top of the winner point, so a correct winner still gives at least 1 pt.

---

## Prediction UI

### Conditional knockout selector

For knockout fixtures (`$fixture->stage->isKnockout()`), the prediction form gains an extra section after the score inputs. Controlled with Alpine.js — no extra server round-trips:

- **Non-draw score** (home ≠ away): `knockout_outcome` is set automatically to `HomeWin` or `AwayWin` via a hidden input. A small label confirms the outcome ("Brazil win in normal time").
- **Draw score** (home === away): a radio/select appears with four options: Home win AET, Away win AET, Home win on penalties, Away win on penalties. Required field.

The team names from `$fixture->homeTeam` / `$fixture->awayTeam` (or placeholders) label the options so users see real team names, not "home/away".

Existing group stage prediction components are untouched.

---

## Results Import Pipeline

### `app/Services/Results/AbstractAiResultsService.php` — `buildPrompt()`

Update the example JSON format to include AET and pens fields:

```json
[
  {
    "id": "01JXXXXXXXXXXXXXXXXXXXXXXXXX",
    "home_score": 1,
    "away_score": 1,
    "home_score_aet": 1,
    "away_score_aet": 2,
    "home_score_pens": null,
    "away_score_pens": null,
    "status": "completed"
  }
]
```

Update the rules section to explain:
- `home_score` / `away_score` = score at 90 mins (including stoppage time, never AET).
- `home_score_aet` / `away_score_aet` = score at end of extra time if the match went to AET; null otherwise.
- `home_score_pens` / `away_score_pens` = number of penalties scored by each team if it went to a shootout; null otherwise.
- Group stage matches never have AET or pens values.

### `app/Services/Results/ResultsResponseParser.php` — `normalise()`

Extend the return shape to include the four new nullable int fields. Update the PHPDoc return type accordingly.

### `app/Console/Commands/SyncResults.php`

Update the `fixture->update()` call to include the four new score columns from the normalised result. Update `outputDummyReport()` table to show AET/pens columns.

### `app/Filament/Resources/FixtureResource.php` — "Import Result" action

Add four optional numeric inputs: `home_score_aet`, `away_score_aet`, `home_score_pens`, `away_score_pens`. Use Alpine to show them only when the 90-min score is a draw (home_score === away_score). Validation: if pens scores are entered, AET scores must also be present. Wire them into the `$record->update()` call.

---

## Files to Create

- `app/Enums/KnockoutOutcome.php`
- `database/migrations/YYYY_MM_DD_HHMMSS_add_knockout_outcome_to_predictions_table.php`
- `tests/Feature/Scoring/KnockoutScoringTest.php`

## Files to Modify

- `app/Models/Prediction.php` — fillable + cast
- `app/Services/Scoring/FixturePredictionScorer.php` — knockout method point
- `app/Services/Scoring/FixturePredictionScore.php` — `knockoutMethodCorrect` field
- `app/Services/Results/AbstractAiResultsService.php` — `buildPrompt()`, `buildRawPrompt()`, `buildAllRawPrompt()`
- `app/Services/Results/ResultsResponseParser.php` — `normalise()` + PHPDoc
- `app/Console/Commands/SyncResults.php` — write AET/pens to DB + dummy report
- `app/Filament/Resources/FixtureResource.php` — AET/pens inputs on import action
- Prediction Livewire component (wherever score input lives) — knockout outcome selector

---

## Verification

### Pest tests (new file: `tests/Feature/Scoring/KnockoutScoringTest.php`)

- `KnockoutOutcome::fromFixture()` resolves all 6 cases correctly
- Scorer awards 5 pts: exact 90-min score + right winner + right method
- Scorer awards 4 pts: exact 90-min score + right method (draw, predicted right winner via pens but scored 1-1 so winner implicit in outcome)
- Scorer awards 3 pts: exact 90-min score + wrong method
- Scorer awards 2 pts: wrong score, right winner + right method
- Scorer awards 1 pt: wrong score, right winner, wrong method
- Scorer awards 0 pts: wrong winner
- Group stage predictions (null `knockout_outcome`) are unaffected — scorer returns existing 0/1/3 behaviour

### Manual checks

1. Filament: enter a knockout result with AET scores (e.g. 1-1 FT, 2-1 AET), confirm fixture saves all four columns
2. Filament: enter a pens result (e.g. 1-1 FT, 1-1 AET, pens 4-3), confirm all six score fields saved correctly
3. Submit a knockout prediction as a draw + penalty resolution, confirm `knockout_outcome` stored
4. Trigger `RecalculateFixturePredictions` on completed knockout fixture, confirm points reflect new logic
5. Run `php artisan world-cup:sync-results --dry-run` on a knockout fixture — confirm AET/pens appear in the preview table
