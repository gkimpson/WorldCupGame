# Prediction Receipt — Design Spec

**Date:** 2026-06-13
**Status:** Approved

## Overview

Enhance the "Your Prediction" card on the fixture detail page (`/fixtures/{fixture}`) to become a full post-match receipt. Once a result is in, authenticated users see their predicted score, an outcome badge, and points earned. A locked-pending state is also added for matches that are locked but not yet played.

No new routes or Livewire components are introduced — this is a targeted enhancement to the existing `ShowFixture` component and its view.

## Feature Scope

### What's in scope
- `PredictionOutcome` enum with four cases: `Exact`, `Correct`, `Incorrect`, `Pending`
- `outcome(Fixture $fixture): PredictionOutcome` method on `Prediction` model
- Outcome badge in the "Your Prediction" card (green = Exact, amber = Correct, red = Incorrect)
- Locked-pending state label ("Locked · Awaiting result") when fixture is locked but has no result
- `ShowFixture::render()` passes computed outcome to the view

### What's out of scope
- Shareable `/predictions/{prediction}` route (belongs to the share cards feature)
- Showing other users' predictions on the fixture page
- Season predictions

## Architecture

### `App\Enums\PredictionOutcome`

```php
enum PredictionOutcome: string
{
    case Exact = 'exact';
    case Correct = 'correct';
    case Incorrect = 'incorrect';
    case Pending = 'pending';
}
```

Static factory method `fromScores(int $predHome, int $predAway, int $actualHome, int $actualAway): self` calculates outcome from raw scores:
- `Exact` — predicted scores match actual scores exactly
- `Correct` — predicted winner/draw matches actual winner/draw, but scores differ
- `Incorrect` — predicted winner/draw does not match actual

### `Prediction::outcome(Fixture $fixture): PredictionOutcome`

Delegates to `PredictionOutcome::fromScores()`. Returns `Pending` if `$fixture->home_score === null`.

### `ShowFixture::render()`

Computes `$outcome` (a `PredictionOutcome|null`) and passes it to the view alongside the existing `$userPrediction`. Null when no prediction exists.

### `show-fixture.blade.php` — "Your Prediction" card

Updated to handle five states:

| State | Rendered output |
|---|---|
| Not authenticated | Card not shown |
| Auth, no prediction, fixture not locked | "Make prediction" CTA *(unchanged)* |
| Auth, no prediction, fixture locked | "No prediction made" *(unchanged)* |
| Auth, has prediction, locked, no result | Score + `flux:badge color="zinc"` "Locked · Awaiting result" |
| Auth, has prediction, result available | Score + outcome badge + points badge *(enhanced)* |

Outcome badge colours:
- `Exact Score` → `color="green"`
- `Correct Outcome` → `color="amber"`
- `Incorrect` → `color="red"`

## Outcome Calculation Logic

Winner/draw is determined by comparing home vs away goals:
- Home win: `home > away`
- Away win: `away > home`
- Draw: `home === away`

`Correct` requires the predicted winner/draw to match the actual winner/draw (outcome match), but the scores to differ. `Exact` requires all four values to match.

## Testing

- Unit tests for `PredictionOutcome::fromScores()` covering all cases: exact, correct outcome (home win / away win / draw), and incorrect
- Unit test for `Prediction::outcome()` returning `Pending` when fixture has no result
- Feature test on `ShowFixture` verifying the correct badge is rendered for each state (exact, correct, incorrect, pending/locked, no prediction)
